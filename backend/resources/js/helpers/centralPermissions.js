/**
 * Platform-operator abilities (App\Enums\CentralPermission), as returned in
 * centralAuth.user.permissions. UI gating only: the API enforces every one of them.
 */
export const CENTRAL_PERMISSIONS = Object.freeze({
    settingsView: 'super_admin.settings.view',
    settingsManage: 'super_admin.settings.manage',
});
