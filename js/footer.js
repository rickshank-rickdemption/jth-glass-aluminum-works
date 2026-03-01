(() => {
    const RECAPTCHA_SITE_KEY = '6LdqwHIsAAAAAEQRzF1v-XzxuFcqqypbIz7fMKfw';

    const mount = document.getElementById('site-footer');
    if (!mount) return;

    function loadRecaptchaScript() {
        if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
            return Promise.resolve();
        }

        const existing = document.querySelector('script[data-recaptcha-v3="true"]');
        if (existing) {
            return new Promise((resolve, reject) => {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('Failed to load reCAPTCHA script.')), { once: true });
            });
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(RECAPTCHA_SITE_KEY)}`;
            script.async = true;
            script.defer = true;
            script.dataset.recaptchaV3 = 'true';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load reCAPTCHA script.'));
            document.head.appendChild(script);
        });
    }

    function getRecaptchaToken(action) {
        return loadRecaptchaScript().then(() => {
            if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') {
                throw new Error('reCAPTCHA is not loaded.');
            }
            return new Promise((resolve, reject) => {
                window.grecaptcha.ready(() => {
                    window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action })
                        .then(resolve)
                        .catch(() => reject(new Error('Failed to generate reCAPTCHA token.')));
                });
            });
        });
    }

    fetch('partials/footer.html')
        .then(res => res.text())
        .then(html => {
            mount.innerHTML = html;
            initFooter();
            document.dispatchEvent(new CustomEvent('jth:footer-ready'));
            const refreshScrollBounds = () => {
                if (window.__jthLenis && typeof window.__jthLenis.resize === 'function') {
                    try { window.__jthLenis.resize(); } catch (_) {}
                }
                try { window.dispatchEvent(new Event('resize')); } catch (_) {}
            };
            requestAnimationFrame(refreshScrollBounds);
            setTimeout(refreshScrollBounds, 120);
            setTimeout(refreshScrollBounds, 320);
        })
        .catch(() => {
        });

    function initFooter() {
        loadRecaptchaScript()
            .then(() => {
                if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                    window.grecaptcha.ready(() => {
                        window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'footer_init' }).catch(() => {});
                    });
                }
            })
            .catch(() => {});

        const form = document.getElementById('contactForm');
        if (!form) return;

        const feedback = document.getElementById('contact-feedback');
        const phoneInput = document.getElementById('contact-phone');
        const fnameInput = document.getElementById('contact-fname');
        const lnameInput = document.getElementById('contact-lname');
        const emailInput = document.getElementById('contact-email');
        const messageInput = document.getElementById('contact-message');
        const consentInput = document.getElementById('contact-consent');
        const submitBtn = document.getElementById('contact-submit-btn');
        const attachmentInput = document.getElementById('contact-attachments');
        const honeypotInput = document.getElementById('contact-website');
        const startedAtInput = document.getElementById('contact-started-at');
        const formStartedAt = Math.floor(Date.now() / 1000);
        if (startedAtInput) startedAtInput.value = String(formStartedAt);

        const toTitleCase = (input) => input.toLowerCase().replace(/\b[a-z]/g, (c) => c.toUpperCase());

        const capitalize = (inputEl) => {
            const words = inputEl.value.split(' ');
            for (let i = 0; i < words.length; i++) {
                if (!words[i]) continue;
                words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
            }
            inputEl.value = words.join(' ');
        };

        const setFeedback = (message, isError) => {
            if (!feedback) return;
            feedback.innerText = message;
            feedback.className = `text-xs font-bold transition-opacity opacity-100 ${isError ? 'text-red-400' : 'text-green-400'}`;
        };
        const createInlineError = (anchorEl) => {
            if (!anchorEl || !anchorEl.parentElement) return null;
            const err = document.createElement('p');
            err.className = 'text-[11px] text-red-400 mt-1 hidden';
            anchorEl.insertAdjacentElement('afterend', err);
            return err;
        };
        const fnameErr = createInlineError(fnameInput);
        const emailErr = createInlineError(emailInput);
        const phoneErr = createInlineError(phoneInput);
        const messageErr = createInlineError(messageInput);
        const consentErr = (() => {
            const wrap = consentInput ? consentInput.closest('.mt-4') : null;
            if (!wrap) return null;
            const err = document.createElement('p');
            err.className = 'text-[11px] text-red-400 mt-1 hidden';
            wrap.appendChild(err);
            return err;
        })();
        const setFieldError = (errEl, message) => {
            if (!errEl) return;
            if (message) {
                errEl.textContent = message;
                errEl.classList.remove('hidden');
            } else {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
        };
        const blockedEmailDomains = [
            'bultoc.com', '10minutemail.com', '10minutemail.net', 'guerrillamail.com',
            'mailinator.com', 'maildrop.cc', 'temp-mail.org', 'tempmail.com', 'yopmail.com',
            'trashmail.com', 'getnada.com', 'mail.tm', 'tmailor.com'
        ];
        const blockedEmailKeywords = [
            'tempmail', 'temp-mail', 'temporary-mail', '10minutemail', 'mailinator',
            'guerrilla', 'yopmail', 'throwaway', 'trashmail', 'fakeinbox', 'burnermail',
            'disposable', 'maildrop', 'mailnesia', 'getnada', 'sharklasers', 'dropmail',
            'inboxkitten', 'spamgourmet', 'spambox', 'mail.tm', 'tmailor'
        ];
        const isDisposableEmail = (email) => {
            const val = String(email || '').trim().toLowerCase();
            const at = val.lastIndexOf('@');
            if (at < 0) return false;
            const domain = val.slice(at + 1);
            if (!domain) return false;
            const exactOrSub = blockedEmailDomains.some((d) => domain === d || domain.endsWith(`.${d}`));
            const keywordHit = blockedEmailKeywords.some((kw) => domain.includes(kw));
            return exactOrSub || keywordHit;
        };
        const isLikelyFakePhone = (phone) => {
            const val = String(phone || '').replace(/\D/g, '');
            if (val === '') return false; // optional
            if (!/^09\d{9}$/.test(val)) return true;
            const sub = val.slice(2);
            if (/^(\d)\1{8}$/.test(sub)) return true;
            if (sub === '123456789' || sub === '987654321') return true;
            const pair = sub.slice(0, 2);
            if (pair && pair.repeat(5).startsWith(sub)) return true;
            const triple = sub.slice(0, 3);
            if (triple && triple.repeat(3).startsWith(sub)) return true;
            return false;
        };
        const validateFirstName = () => {
            const value = fnameInput.value.trim();
            if (value.length < 2) {
                setFieldError(fnameErr, 'Please enter your first name.');
                return false;
            }
            setFieldError(fnameErr, '');
            return true;
        };
        const validateEmail = () => {
            const value = emailInput.value.trim();
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(value)) {
                setFieldError(emailErr, 'Please enter a valid email address.');
                return false;
            }
            if (isDisposableEmail(value)) {
                setFieldError(emailErr, 'Disposable email is not allowed. Use a real and existing email.');
                return false;
            }
            setFieldError(emailErr, '');
            return true;
        };
        const validatePhone = () => {
            const value = phoneInput.value.trim();
            if (value !== '' && !/^09\d{9}$/.test(value)) {
                setFieldError(phoneErr, 'Use a valid PH mobile number (11 digits starting with 09).');
                return false;
            }
            if (isLikelyFakePhone(value)) {
                setFieldError(phoneErr, 'Phone number looks invalid. Please use a real mobile number.');
                return false;
            }
            setFieldError(phoneErr, '');
            return true;
        };
        const validateMessage = () => {
            if (messageInput.value.trim().length < 3) {
                setFieldError(messageErr, 'Please enter a short message.');
                return false;
            }
            setFieldError(messageErr, '');
            return true;
        };
        const validateConsent = () => {
            if (!consentInput || consentInput.checked !== true) {
                setFieldError(consentErr, 'Consent is required to continue.');
                return false;
            }
            setFieldError(consentErr, '');
            return true;
        };
        const clearAllInlineErrors = () => {
            [fnameErr, emailErr, phoneErr, messageErr, consentErr].forEach((el) => setFieldError(el, ''));
        };

        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
            validatePhone();
        });

        [fnameInput, lnameInput].forEach(input => {
            input.addEventListener('input', () => capitalize(input));
        });
        fnameInput.addEventListener('input', validateFirstName);
        emailInput.addEventListener('input', validateEmail);
        messageInput.addEventListener('input', validateMessage);
        consentInput.addEventListener('change', validateConsent);

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const originalText = submitBtn.innerText;
            const emailVal = emailInput.value.trim();
            const phoneVal = phoneInput.value.trim();
            clearAllInlineErrors();

            if (!validateFirstName()) {
                setFeedback('Error: Please check the highlighted fields.', true);
                return;
            }

            if (!validatePhone()) {
                setFeedback('Error: Please check the highlighted fields.', true);
                return;
            }

            if (!validateEmail()) {
                setFeedback('Error: Please check the highlighted fields.', true);
                return;
            }

            if (!validateMessage()) {
                setFeedback('Error: Please check the highlighted fields.', true);
                return;
            }
            if (!validateConsent()) {
                setFeedback('Error: Please check the highlighted fields.', true);
                return;
            }
            const files = attachmentInput && attachmentInput.files ? Array.from(attachmentInput.files) : [];
            if (files.length > 3) {
                setFeedback('Error: Maximum of 3 attachments only.', true);
                return;
            }
            const maxBytes = 5 * 1024 * 1024;
            const hasOversized = files.some((f) => (f.size || 0) > maxBytes);
            if (hasOversized) {
                setFeedback('Error: Each attachment must be up to 5MB.', true);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerText = 'SENDING...';
            if (feedback) feedback.classList.add('opacity-0');

            try {
                const recaptchaToken = await getRecaptchaToken('contact_inquiry');
                const formData = new FormData();
                formData.append('fname', fnameInput.value.trim());
                formData.append('lname', lnameInput.value.trim());
                formData.append('email', emailVal);
                formData.append('phone', phoneVal);
                formData.append('message', messageInput.value.trim());
                formData.append('consent', consentInput.checked === true ? '1' : '0');
                formData.append('website', honeypotInput ? honeypotInput.value.trim() : '');
                formData.append('form_started_at', String(startedAtInput ? parseInt(startedAtInput.value, 10) || formStartedAt : formStartedAt));
                formData.append('recaptcha_token', recaptchaToken);
                files.forEach((file) => formData.append('attachments[]', file));

                const response = await fetch('backend/contact.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    setFeedback("Message Sent! We'll be in touch.", false);
                    form.reset();
                    submitBtn.innerText = 'SUCCESS';
                } else {
                    if (result.reason === 'rate_limited') {
                        const wait = parseInt(result.retry_after_seconds, 10) || 60;
                        throw new Error(`Too many attempts. Please wait ${wait}s and try again.`);
                    }
                    throw new Error(result.message || 'An error occurred.');
                }
            } catch (error) {
                setFeedback(error.message, true);
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
        });
    }
})();
