// ─── Afghan Solar-Hijri (خورشیدی) calendar ─────────────────────────────────
// Afghanistan uses the same solar calendar as Iran but with the ORIGINAL Persian
// month names (حمل، ثور…), never the Iranian ones (فروردین…). All date display in
// the app shows both calendars side by side; a user preference decides which one
// leads (default = Gregorian / میلادی).
export const AFGHAN_MONTHS = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت']

export function toFarsiDigits(str) {
  return String(str).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d])
}

// Accurate Gregorian ⇄ solar-Hijri conversion (the widely-used jdf algorithm).
const _div = (a, b) => ~~(a / b)

/** Gregorian Date → { y, m, d } in the Afghan/Persian solar calendar. */
export function toShamsi(gDate) {
  const gy = gDate.getFullYear()
  const gm = gDate.getMonth() + 1
  const gd = gDate.getDate()
  const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  const gy2 = gm > 2 ? gy + 1 : gy
  let days = 355666 + 365 * gy + _div(gy2 + 3, 4) - _div(gy2 + 99, 100) + _div(gy2 + 399, 400) + gd + gdm[gm - 1]
  let jy = -1595 + 33 * _div(days, 12053)
  days %= 12053
  jy += 4 * _div(days, 1461)
  days %= 1461
  if (days > 365) { jy += _div(days - 1, 365); days = (days - 1) % 365 }
  let jm, jd
  if (days < 186) { jm = 1 + _div(days, 31); jd = 1 + (days % 31) }
  else { jm = 7 + _div(days - 186, 30); jd = 1 + ((days - 186) % 30) }
  return { y: jy, m: jm, d: jd }
}

/** Afghan/Persian solar (jy, jm, jd) → "YYYY-MM-DD" Gregorian. */
export function toGregorian(jy, jm, jd) {
  const jy2 = jy + 1595
  let days = -355668 + 365 * jy2 + _div(jy2, 33) * 8 + _div((jy2 % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186)
  let gy = 400 * _div(days, 146097)
  days %= 146097
  if (days > 36524) { gy += 100 * _div(--days, 36524); days %= 36524; if (days >= 365) days++ }
  gy += 4 * _div(days, 1461)
  days %= 1461
  if (days > 365) { gy += _div(days - 1, 365); days = (days - 1) % 365 }
  let gd = days + 1
  const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0
  const sal = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  let gm
  for (gm = 0; gm < 13; gm++) { if (gd <= sal[gm]) break; gd -= sal[gm] }
  return `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
}

/** Days in a given Afghan/Persian solar month (1..12). */
export function shamsiMonthLength(jy, jm) {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  // Esfand/حوت: 30 in a leap year, else 29. Derive from the calendar itself.
  const firstNext = new Date(toGregorian(jy + 1, 1, 1))
  const lastHoot = new Date(firstNext.getTime() - 86400000)
  return toShamsi(lastHoot).d
}

/** "۲۹ سرطان ۱۴۰۵" — Afghan solar date, Farsi digits. */
export function shamsiDate(val) {
  if (!val || val === '—') return ''
  const d = new Date(val)
  if (isNaN(d.getTime())) return ''
  const s = toShamsi(d)
  return toFarsiDigits(`${s.d} ${AFGHAN_MONTHS[s.m - 1]} ${s.y}`)
}

function gregDate(d) {
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

/** Which calendar leads. Set from the profile menu / Control Room; default میلادی. */
export function calendarPref() {
  try { return localStorage.getItem('calendar_type') === 'fa' ? 'fa' : 'en' } catch { return 'en' }
}

/**
 * Both calendars in one string, primary first per the user preference.
 *   default →  "20 Jun 2026 · ۲۹ سرطان ۱۴۰۵"
 *   fa lead →  "۲۹ سرطان ۱۴۰۵ · 20 Jun 2026"
 */
export function dualDate(val) {
  if (!val || val === '—') return '—'
  const d = new Date(val)
  if (isNaN(d.getTime())) return String(val).slice(0, 10) || '—'
  const g = gregDate(d)
  const s = shamsiDate(d)
  if (!s) return g
  return calendarPref() === 'fa' ? `${s} · ${g}` : `${g} · ${s}`
}

/**
 * Format any date value into a clean display string — now with the Afghan solar
 * date shown beside the Gregorian one everywhere the app renders a date.
 *   "2026-06-20T00:00:00Z"  →  "20 Jun 2026 · ۲۹ جوزا ۱۴۰۵"
 *   null / undefined / ''    →  "—"
 */
export function fmtDate(val) {
  return dualDate(val)
}

/** Plain Gregorian only (no Shamsi) — for tight spots that opt out. */
export function fmtDateGregorian(val) {
  if (!val || val === '—') return '—'
  const d = new Date(val)
  if (isNaN(d.getTime())) return String(val).slice(0, 10) || '—'
  return gregDate(d)
}

/**
 * Format date + time, with the Afghan solar date appended.
 * "2026-06-20T14:35:00Z"  →  "20 Jun 2026 14:35 · ۲۹ جوزا ۱۴۰۵"
 */
export function fmtDateTime(val) {
  if (!val || val === '—') return '—'
  const d = new Date(val)
  if (isNaN(d.getTime())) return String(val).slice(0, 16) || '—'
  const date = gregDate(d)
  const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  const s = shamsiDate(d)
  return s ? `${date} ${time} · ${s}` : `${date} ${time}`
}

/** Column names that should be auto-formatted as dates in DataTable */
const DATE_COL_NAMES = new Set([
  'date', 'join_date', 'date_of_birth', 'dob', 'start_date', 'end_date',
  'due_date', 'expiry_date', 'delivery_date', 'issue_date', 'payment_date',
  'updated_at',
])

const DATE_SUFFIX_RE = /_date$|_at$/

/**
 * Returns true if a column's name looks like a date field
 * (but NOT created_at which is used as a row-index counter).
 */
export function isDateColumn(colName) {
  if (colName === 'created_at') return false
  return DATE_COL_NAMES.has(colName) || DATE_SUFFIX_RE.test(colName)
}
