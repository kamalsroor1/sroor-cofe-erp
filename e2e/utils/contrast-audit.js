// COLOR-01 contrast audit: collects every visible text node (plus input values / placeholders) with its
// effective foreground and background layers from the real page, then scores it with the WCAG 2.1 maths in
// resources/js/helpers/contrast.js. Text over gradients or images cannot be scored from CSS alone, so it is
// counted separately (`skipped`) instead of being guessed.
import {
  contrastRatio,
  flattenLayers,
  requiredRatio,
} from "../../backend/resources/js/helpers/contrast.js";

const CANVAS_BASE = { light: [255, 255, 255], dark: [2, 6, 23] };

/** Runs in the browser: one record per text node with rgba layers, no maths. */
function collectInPage() {
  const canvas = document.createElement("canvas");
  canvas.width = canvas.height = 1;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  const cache = new Map();

  // Chrome serialises Tailwind v4 colours as oklch()/color-mix(); a 1px canvas turns any CSS colour into sRGB.
  const toRgba = (css) => {
    if (cache.has(css)) return cache.get(css);
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = "#000";
    ctx.fillStyle = css;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
    // Canvas returns premultiplied-then-unpremultiplied bytes; alpha < 1 is rare here and rounds fine.
    const value = [r, g, b, +(a / 255).toFixed(3)];
    cache.set(css, value);
    return value;
  };

  const visible = (el) => {
    const rect = el.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2) return false;
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === "none" || s.visibility === "hidden") return false;
      if (n.hasAttribute("hidden") || n.getAttribute("aria-hidden") === "true")
        return false;
      if (+s.opacity === 0) return false;
    }
    return true;
  };

  const path = (el) => {
    const parts = [];
    for (
      let n = el;
      n && n.nodeType === 1 && parts.length < 4;
      n = n.parentElement
    ) {
      const id = n.getAttribute("data-testid");
      const cls = [...n.classList]
        .filter((c) => /^(text-|dark:text-|bg-)/.test(c))
        .slice(0, 4)
        .join(".");
      parts.unshift(
        `${n.tagName.toLowerCase()}${id ? `[${id}]` : ""}${cls ? `.${cls}` : ""}`,
      );
    }
    return parts.join(" > ");
  };

  const describe = (el, text, fgCss) => {
    const style = getComputedStyle(el);
    const layers = []; // bottom-first once reversed
    let opacity = 1;
    let unknown = null;
    const chain = [];
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) chain.push(n);
    // Opacity from every ancestor above (and including) a node dims that node's own background too.
    const suffix = [];
    let acc = 1;
    for (let i = chain.length - 1; i >= 0; i--) {
      acc *= +getComputedStyle(chain[i]).opacity;
      suffix[i] = acc;
    }
    opacity = suffix[0];
    chain.forEach((n, i) => {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== "none" && !unknown) {
        unknown = s.backgroundImage.includes("gradient") ? "gradient" : "image";
      }
      const bg = toRgba(s.backgroundColor);
      if (bg[3] > 0) layers.push([bg[0], bg[1], bg[2], bg[3] * suffix[i]]);
    });
    const fg = toRgba(fgCss);
    return {
      text: text.trim().slice(0, 40),
      fg: [fg[0], fg[1], fg[2], fg[3] * opacity],
      layers: layers.reverse(),
      size: parseFloat(style.fontSize),
      weight: Number(style.fontWeight) || 400,
      unknown,
      path: path(el),
    };
  };

  const covered = (el, rect) => {
    const x = rect.left + rect.width / 2;
    const y = rect.top + rect.height / 2;
    if (x < 0 || y < 0 || x > innerWidth || y > innerHeight) return false;
    const hit = document.elementFromPoint(x, y);
    return !!hit && !el.contains(hit) && !hit.contains(el);
  };

  const records = [];
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    if (!node.nodeValue.trim()) continue;
    const el = node.parentElement;
    if (!el || ["SCRIPT", "STYLE", "NOSCRIPT", "OPTION"].includes(el.tagName))
      continue;
    if (el.closest("textarea, select, [disabled], [aria-disabled='true']"))
      continue;
    if (!visible(el)) continue;
    const range = document.createRange();
    range.selectNodeContents(node);
    const rect = range.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2 || covered(el, rect)) continue;
    records.push(describe(el, node.nodeValue, getComputedStyle(el).color));
  }

  document
    .querySelectorAll("input:not([disabled]), textarea:not([disabled])")
    .forEach((el) => {
      if (
        ["hidden", "checkbox", "radio", "file", "range", "color"].includes(
          el.type,
        )
      )
        return;
      if (!visible(el)) return;
      if (el.value)
        records.push(describe(el, el.value, getComputedStyle(el).color));
      if (el.placeholder) {
        records.push(
          describe(
            el,
            el.placeholder,
            getComputedStyle(el, "::placeholder").color,
          ),
        );
      }
    });

  return records;
}

/** Scores one page. `mode` is "light" | "dark" (selects the canvas colour behind everything). */
/** Let colour transitions and enter animations finish; mid-transition colours are not what users settle on. */
async function settle(page) {
  for (let pass = 0; pass < 3; pass++) {
    const pending = await page.evaluate(async () => {
      const running = document
        .getAnimations()
        .filter((a) => a.effect?.getComputedTiming().iterations !== Infinity);
      await Promise.all(running.map((a) => a.finished.catch(() => {})));
      return running.length;
    });
    if (pending === 0) return;
  }
}

export async function auditPage(page, mode) {
  await settle(page);
  const records = await page.evaluate(collectInPage);
  const base = CANVAS_BASE[mode];
  const failing = [];
  let scored = 0;
  let skipped = 0;

  for (const r of records) {
    if (r.unknown) {
      skipped += 1;
      continue;
    }
    scored += 1;
    const bg = flattenLayers(r.layers, base);
    const ratio = contrastRatio(r.fg, bg);
    const min = requiredRatio(r.size, r.weight);
    if (ratio + 0.005 < min) {
      failing.push({
        text: r.text,
        ratio: +ratio.toFixed(2),
        min,
        size: r.size,
        path: r.path,
      });
    }
  }

  return {
    total: scored,
    skipped,
    failing,
    percent: scored ? (failing.length / scored) * 100 : 0,
  };
}

/** Collapse failing records into "path -> count" so a report names the offending class. */
export function groupFailures(failing) {
  const groups = new Map();
  for (const f of failing) {
    const g = groups.get(f.path) ?? {
      path: f.path,
      count: 0,
      worst: 99,
      sample: f.text,
    };
    g.count += 1;
    g.worst = Math.min(g.worst, f.ratio);
    groups.set(f.path, g);
  }
  return [...groups.values()].sort((a, b) => b.count - a.count);
}

/** Tenant screens covered by the audit (read-only; the POS is audited as evidence only). */
export const AUDIT_ROUTES = [
  { name: "dashboard", path: "/" },
  { name: "invoices", path: "/invoices" },
  { name: "items", path: "/items" },
  { name: "categories", path: "/categories" },
  { name: "stores", path: "/stores" },
  { name: "stores-stocks", path: "/stores/stocks" },
  { name: "customers", path: "/customers" },
  { name: "suppliers", path: "/suppliers" },
  { name: "purchases", path: "/purchases" },
  { name: "expenses", path: "/expenses" },
  { name: "returns", path: "/returns" },
  { name: "stock-transfers", path: "/stock-transfers" },
  { name: "coffee-blender", path: "/coffee-blender" },
  { name: "daily-journal", path: "/daily-journal" },
  { name: "reports", path: "/reports" },
  { name: "users", path: "/users" },
  { name: "activity-logs", path: "/activity-logs" },
  { name: "profile", path: "/profile" },
  { name: "trash", path: "/trash" },
  { name: "pos", path: "/pos" },
];
