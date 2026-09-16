import { createHmac } from 'node:crypto';

/**
 * RFC 6238, the twenty lines of it the plugin uses.
 *
 * The authenticator app is the one piece of the second step that lives outside
 * the browser, so the test has to be the app: it reads the secret off the
 * screen where a person would point their phone, and works out the same six
 * digits. Same parameters as includes/auth-totp.php — SHA-1, six digits,
 * thirty seconds — because a test with different ones proves nothing.
 */

const DIGITS = 6;
const STEP = 30;

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Base32 as RFC 4648, which is what the otpauth: URI carries. */
export function base32Decode(secret: string): Buffer {
	const clean = secret.toUpperCase().replace(/[^A-Z2-7]/g, '');
	const bytes: number[] = [];
	let bits = 0;
	let value = 0;

	for (const character of clean) {
		value = (value << 5) | ALPHABET.indexOf(character);
		bits += 5;

		if (bits >= 8) {
			bits -= 8;
			bytes.push((value >>> bits) & 0xff);
		}
	}

	return Buffer.from(bytes);
}

/** The six digits for a moment in time — now, unless told otherwise. */
export function totp(secret: string, when: number = Date.now()): string {
	const counter = Math.floor(when / 1000 / STEP);
	const message = Buffer.alloc(8);

	message.writeUInt32BE(Math.floor(counter / 2 ** 32), 0);
	message.writeUInt32BE(counter >>> 0, 4);

	const hash = createHmac('sha1', base32Decode(secret)).update(message).digest();
	const offset = hash[hash.length - 1] & 0x0f;
	const number =
		((hash[offset] & 0x7f) << 24) |
		((hash[offset + 1] & 0xff) << 16) |
		((hash[offset + 2] & 0xff) << 8) |
		(hash[offset + 3] & 0xff);

	return String(number % 10 ** DIGITS).padStart(DIGITS, '0');
}

/**
 * A code that is valid but is not the current one.
 *
 * The plugin refuses a code it has already seen, so a test that needs a second
 * good code inside the same thirty seconds cannot ask for the same one twice.
 * One step back is still inside the ±1 window the verifier allows.
 */
export function totpPrevious(secret: string, when: number = Date.now()): string {
	return totp(secret, when - STEP * 1000);
}

/** Six digits that are certainly not the right ones. */
export function wrongCode(secret: string): string {
	const right = totp(secret);
	const wrong = String((Number(right) + 111111) % 1_000_000).padStart(DIGITS, '0');

	return wrong === right ? '000000' : wrong;
}

/** How far into the current thirty-second window we are, in milliseconds. */
export function millisIntoStep(when: number = Date.now()): number {
	return when % (STEP * 1000);
}

/**
 * Waits for a fresh window when the current one is nearly over.
 *
 * A code read at second 29 is wrong by the time the form is submitted, and a
 * test that fails one run in sixty is a test nobody trusts.
 */
export async function avoidWindowEdge(marginMs = 3000): Promise<void> {
	const left = STEP * 1000 - millisIntoStep();

	if (left < marginMs) {
		await new Promise((resolve) => setTimeout(resolve, left + 250));
	}
}
