<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accept Invitation — Prince Byron Admin</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body style="background: var(--color-linen); font-family: var(--font-sans); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;">
    <div style="background: var(--surface-card); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 40px; max-width: 420px; width: 92vw; box-shadow: var(--shadow-lg);">
        <h1 style="font-family: var(--font-display); color: var(--color-obsidian); margin: 0 0 16px; font-size: 1.5rem;">Join the Admin Team</h1>
        <p style="color: var(--text-muted); margin: 0 0 24px; line-height: 1.6;">
            You&rsquo;ve been invited to join the Prince Byron admin panel as a
            <strong>{{ ucfirst($invitation->level) }}</strong>.
        </p>

        <form id="acceptForm" style="display: flex; flex-direction: column; gap: 16px;">
            <div>
                <label for="name" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Full Name</label>
                <input type="text" id="name" name="name" required
                    style="width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-input); border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; background: var(--surface-card); color: var(--text-primary); box-sizing: border-box;">
            </div>

            <div>
                <label for="email" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Email Address</label>
                <input type="email" id="email" name="email" value="{{ $invitation->email }}" readonly
                    style="width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-input); border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; background: var(--color-paper); color: var(--text-muted); box-sizing: border-box;">
            </div>

            <div>
                <label for="password" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Password (min 8 chars)</label>
                <input type="password" id="password" name="password" required
                    style="width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-input); border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; background: var(--surface-card); color: var(--text-primary); box-sizing: border-box;">
            </div>

            <div>
                <label for="password_confirmation" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    style="width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-input); border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; background: var(--surface-card); color: var(--text-primary); box-sizing: border-box;">
            </div>

            <p style="font-size: 0.78rem; color: var(--text-muted); margin: 8px 0 0; line-height: 1.5;">
                This invitation expires on {{ $invitation->expires_at->format('F j, Y') }} and can only be used once.
            </p>

            <button type="submit"
                style="background: var(--color-clay); color: var(--color-white); border: none; padding: 14px 24px; border-radius: var(--radius-sm); font-family: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer; margin-top: 8px;">
                Create Account &amp; Accept Invitation
            </button>
        </form>
    </div>

    <script>
        document.getElementById('acceptForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const original = btn.innerHTML;
            btn.innerHTML = 'Creating&hellip;';
            btn.disabled = true;

            const formData = new FormData(e.target);
            const response = await fetch('{{ url("/admin/staff/invitations/" . $invitation->token . "/accept") }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: formData,
            });

            const result = await response.json();

            if (response.ok) {
                btn.innerHTML = 'Created! Redirecting&hellip;';
                btn.style.background = 'var(--color-gold)';
                setTimeout(() => {
                    window.location.href = result.redirect ?? '/admin/login';
                }, 800);
            } else {
                btn.innerHTML = original;
                btn.disabled = false;
                alert(result.message ?? 'Something went wrong. Please try again.');
            }
        });
    </script>
</body>
</html>
