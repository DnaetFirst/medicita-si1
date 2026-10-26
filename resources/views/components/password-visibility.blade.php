<style>
    .password-field { position: relative; }
    .password-field input { padding-right: 3rem; }
    .password-toggle {
        position: absolute;
        right: 0.25rem;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: var(--color-text-muted);
        cursor: pointer;
    }
    .password-toggle:hover { color: var(--color-primary); }
    .password-toggle:focus-visible { outline: 2px solid var(--color-secondary); }
</style>
<script>
    document.querySelectorAll('input[type="password"]').forEach((input, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field';
        input.before(wrapper);
        wrapper.append(input);

        if (!input.id) {
            input.id = `password-field-${index}`;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', 'Mostrar contraseña');
        button.title = 'Mostrar contraseña';
        button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path data-eye-slash d="m3 3 18 18" style="display:none"/></svg>';
        button.addEventListener('click', () => {
            const isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            const label = isVisible ? 'Ocultar contraseña' : 'Mostrar contraseña';
            button.setAttribute('aria-pressed', String(isVisible));
            button.setAttribute('aria-label', label);
            button.title = label;
            button.querySelector('[data-eye-slash]').style.display = isVisible ? '' : 'none';
        });
        wrapper.append(button);
    });
</script>
