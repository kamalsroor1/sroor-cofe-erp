// Single source of truth for the app-shell breakpoints. They mirror Tailwind's default `lg` / `xl`
// screens, so utility classes (`lg:`, `xl:`) and the JS media queries below always agree.
//   < DESKTOP_MIN       mobile + tablet: no fixed sidebar, drawer + bottom nav
//   DESKTOP_MIN..WIDE   sidebar collapsed to a rail; expanding opens an overlay
//   >= WIDE_MIN         sidebar open (remembered per user)
export const PHONE_MIN = 768;
export const DESKTOP_MIN = 1024;
export const WIDE_MIN = 1280;

export const DESKTOP_QUERY = `(min-width: ${DESKTOP_MIN}px)`;
// Exact complement of DESKTOP_QUERY. Phones and tablets: the POS swaps its split layout for the cart bar + sheets.
export const COMPACT_QUERY = `(max-width: ${DESKTOP_MIN - 0.02}px)`;
export const WIDE_QUERY = `(min-width: ${WIDE_MIN}px)`;
