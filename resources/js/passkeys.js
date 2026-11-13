// Passkey sign-in, confirmation and registration through Fortify's endpoints, using Laravel's official
// browser client (@laravel/passkeys). It reads the CSRF token from the layout's <meta name="csrf-token">.
//
// The client is loaded on demand (cleanup batch 6): the dashboard bundle no longer carries it on every page, only
// when a passkey button is used. Sign-in pages start loading it at once (preloadPasskeys), so there's no wait.
let client;
const passkeys = () => (client ??= import('@laravel/passkeys').then((module) => module.Passkeys));

export function preloadPasskeys() {
    passkeys();
}

// The same check as the client's Passkeys.isSupported() (@simplewebauthn/browser's browserSupportsWebAuthn),
// so the components can show or hide themselves before the client has loaded.
const isSupported = () => typeof globalThis.PublicKeyCredential === 'function';

const cancelled = (error) => error?.name === 'NotAllowedError' || error?.name === 'AbortError';

export function registerPasskeys(Alpine) {
    Alpine.data('passkeyLogin', () => ({
        supported: isSupported(),
        busy: false,
        error: '',
        async signIn() {
            this.busy = true;
            this.error = '';
            try {
                const response = await (await passkeys()).verify();
                window.location.assign(response?.redirect ?? '/dashboard');
            } catch (error) {
                this.error = cancelled(error)
                    ? 'Passkey sign-in was cancelled.'
                    : "That passkey didn't work. Try again, or sign in with your password.";
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('passkeyConfirm', () => ({
        supported: isSupported(),
        busy: false,
        error: '',
        async confirm() {
            this.busy = true;
            this.error = '';
            try {
                const response = await (await passkeys()).verify({
                    routes: { options: '/passkeys/confirm/options', submit: '/passkeys/confirm' },
                });
                window.location.assign(response?.redirect ?? '/dashboard');
            } catch (error) {
                this.error = cancelled(error) ? 'Confirmation was cancelled.' : "That passkey didn't work. Use your password instead.";
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('passkeyRegister', () => ({
        supported: isSupported(),
        name: '',
        busy: false,
        error: '',
        async register() {
            if (this.name.trim() === '') {
                this.error = 'Give this passkey a name, such as "Work laptop".';
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                await (await passkeys()).register({ name: this.name.trim() });
                window.location.reload();
            } catch (error) {
                this.error = cancelled(error) ? 'Passkey setup was cancelled.' : "The passkey couldn't be added. Please try again.";
            } finally {
                this.busy = false;
            }
        },
    }));
}
