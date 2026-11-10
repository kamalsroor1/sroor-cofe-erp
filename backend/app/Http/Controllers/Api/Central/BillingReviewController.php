<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Billing\CreatePaymentReceiptLinkAction;
use App\Actions\Billing\GetPaymentReceiptFileAction;
use App\Actions\Billing\ListBillingPaymentsAction;
use App\Actions\Billing\RejectManualPaymentAction;
use App\Actions\Billing\VerifyManualPaymentAction;
use App\DTOs\Billing\RejectManualPaymentDTO;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ListBillingPaymentsRequest;
use App\Http\Requests\Central\RejectBillingPaymentRequest;
use App\Http\Resources\Billing\BillingPaymentResource;
use App\Models\BillingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual payment review (PRD-03 / ENTI-3.4), routes/central.php:
 *   GET  /api/v1/super-admin/billing/payments                       super_admin.billing.view
 *   GET  /api/v1/super-admin/billing/payments/{payment}/receipt     super_admin.billing.view  (signed link)
 *   GET  .../payments/{payment}/receipt/file                        signed URL only, SINGLE-USE (streams the file)
 *   POST /api/v1/super-admin/billing/payments/{payment}/verify      super_admin.billing.manage + step-up
 *   POST /api/v1/super-admin/billing/payments/{payment}/reject      super_admin.billing.manage + step-up
 */
final class BillingReviewController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly ListBillingPaymentsAction $listAction,
        private readonly CreatePaymentReceiptLinkAction $linkAction,
        private readonly GetPaymentReceiptFileAction $fileAction,
        private readonly VerifyManualPaymentAction $verifyAction,
        private readonly RejectManualPaymentAction $rejectAction,
    ) {}

    public function index(ListBillingPaymentsRequest $request)
    {
        return BillingPaymentResource::collection($this->listAction->execute($request))
            ->additional(['success' => true]);
    }

    public function receipt(Request $request, int $payment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->linkAction->execute($payment, $this->operator($request)),
        ]);
    }

    /** Signed route: the `signed` middleware has validated the signature and expiry. */
    public function receiptFile(Request $request, int $payment): StreamedResponse
    {
        $media = $this->fileAction->execute($payment, (int) $request->query('u'), (string) $request->query('n'));

        // PDFs are forced to download; images may render inline.
        $disposition = $media->mime_type === 'application/pdf' ? 'attachment' : 'inline';

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), 'receipt-'.$payment.'.'.$media->extension, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => $disposition.'; filename="receipt-'.$payment.'.'.$media->extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verify(Request $request, int $payment): JsonResponse
    {
        $verified = $this->verifyAction->execute($payment, $this->operator($request));

        return $this->respond($verified, __('billing.messages.payment_verified'));
    }

    public function reject(RejectBillingPaymentRequest $request, int $payment): JsonResponse
    {
        $rejected = $this->rejectAction->execute(RejectManualPaymentDTO::fromArray($request->validated(), $payment), $this->operator($request));

        return $this->respond($rejected, __('billing.messages.payment_rejected'));
    }

    private function respond(BillingPayment $payment, string $message): JsonResponse
    {
        return (new BillingPaymentResource($payment->load(['tenant', 'invoice', 'verifier'])))
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }
}
