/**
 * Rupiah Formatting & Integer-Safe Arithmetic Validation
 *
 * Requirements:
 * 1. Financial domain uses non-negative whole Rupiah (integer).
 * 2. Floating-point arithmetic and parseFloat MUST NOT be used for amounts.
 * 3. Form input must validate that amounts are strictly positive whole Rupiah.
 */

/**
 * Format a numeric amount as Indonesian Rupiah (e.g. "Rp 1.500.000").
 * Safe for integer numbers, strings, and zero.
 */
export function formatRupiah(
  amount: number | string | bigint | null | undefined,
  includePrefix = true
): string {
  if (amount === null || amount === undefined || amount === '') {
    return '-';
  }

  const numericValue = typeof amount === 'bigint' ? Number(amount) : Number(amount);

  if (Number.isNaN(numericValue)) {
    return '-';
  }

  // Integer-safe formatting using Indonesian locale without decimal digits
  const formatted = Math.round(numericValue).toLocaleString('id-ID');
  return includePrefix ? `Rp ${formatted}` : formatted;
}

export interface ParseRupiahResult {
  valid: boolean;
  value?: number;
  error?: string;
}

/**
 * Validates and parses user input for monetary transactions into a positive whole Rupiah integer.
 *
 * Rules:
 * - Rejects empty or whitespace-only strings.
 * - Rejects negative values or negative signs.
 * - Rejects zero (amount must be strictly positive).
 * - Rejects decimal fractions (cents/sen are not used in cash ledger).
 * - Strips optional "Rp", "rp", spaces, and thousand-separating dots.
 * - Enforces safe integer bounds.
 */
export function parseIntegerRupiah(
  input: string | number | null | undefined,
  options?: { allowZero?: boolean }
): ParseRupiahResult {
  const allowZero = options?.allowZero ?? false;
  if (input === null || input === undefined) {
    if (allowZero) {
      return { valid: true, value: 0 };
    }
    return { valid: false, error: 'Jumlah nominal wajib diisi.' };
  }

  if (typeof input === 'number') {
    if (!Number.isFinite(input) || !Number.isInteger(input)) {
      return { valid: false, error: 'Nominal harus berupa bilangan bulat rupiah.' };
    }
    if (allowZero ? input < 0 : input <= 0) {
      return { valid: false, error: allowZero ? 'Nominal tidak boleh bernilai negatif.' : 'Nominal harus lebih besar dari 0.' };
    }
    if (input > Number.MAX_SAFE_INTEGER) {
      return { valid: false, error: 'Nominal melebihi batas maksimum transaksi.' };
    }
    return { valid: true, value: input };
  }

  const raw = input.trim();
  if (raw === '') {
    return { valid: false, error: 'Jumlah nominal wajib diisi.' };
  }

  // Check for negative sign
  if (raw.startsWith('-') || raw.includes('-')) {
    return { valid: false, error: 'Nominal tidak boleh bernilai negatif.' };
  }

  // Remove optional "Rp" / "rp" prefix and spaces
  let cleaned = raw.replace(/^rp\s*/i, '').trim();

  // If input contains decimals (e.g. "100.50" or "100,50"), reject
  // Note: dots used strictly as thousand separators (e.g. 1.000.000) are allowed,
  // but commas with decimals or trailing decimals are rejected.
  if (cleaned.includes(',')) {
    return { valid: false, error: 'Nominal harus berupa bilangan bulat rupiah (tanpa pecahan/koma).' };
  }

  // Check if dots are thousand separators: if every dot is followed by 3 digits
  // If dot is used as a decimal point, reject
  if (/\.\d{1,2}$/.test(cleaned)) {
    return { valid: false, error: 'Nominal harus berupa bilangan bulat rupiah (tanpa sen).' };
  }

  // Strip thousand-separator dots
  cleaned = cleaned.replace(/\./g, '').trim();

  // Validate that only numeric digits remain
  if (!/^\d+$/.test(cleaned)) {
    return { valid: false, error: 'Nominal hanya boleh memuat angka.' };
  }

  // Convert to BigInt to safely test bounds and zero
  try {
    const bigVal = BigInt(cleaned);
    if (allowZero ? bigVal < 0n : bigVal <= 0n) {
      return { valid: false, error: allowZero ? 'Nominal tidak boleh bernilai negatif.' : 'Nominal harus lebih besar dari 0.' };
    }
    if (bigVal > BigInt(Number.MAX_SAFE_INTEGER)) {
      return { valid: false, error: 'Nominal melebihi batas maksimum transaksi.' };
    }

    return { valid: true, value: Number(bigVal) };
  } catch {
    return { valid: false, error: 'Format nominal tidak valid.' };
  }
}
