<div class="p-6 space-y-6">

    <div>
        <h3 class="text-lg font-medium text-gray-900">Staff Invitations</h3>
        <p class="text-sm text-gray-500 mt-1">
            Invite staff members to the admin panel with role-based access.
        </p>
    </div>

    <!-- Invitations table -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invited By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($invitations as $invitation)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $invitation['email'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
                                {{ $invitation['level'] === 'owner' ? 'bg-rose-100 text-rose-800' : '' }}
                                {{ $invitation['level'] === 'manager' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $invitation['level'] === 'staff' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $invitation['level'] === 'viewer' ? 'bg-gray-100 text-gray-800' : '' }}
                            ">
                                {{ ucfirst($invitation['level']) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $invitation['invited_by'] ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($invitation['expires_at'])->format('M j, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if ($invitation['is_accepted'])
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                    Accepted
                                </span>
                            @elseif ($invitation['is_expired'])
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                    Expired
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if ($invitation['is_claimable'])
                                <button type="button"
                                    onclick="revokeInvitation(event, {{ $invitation['id'] }}, '{{ $invitation['email'] }}')"
                                    class="text-xs font-medium text-red-600 hover:text-red-800 underline">
                                    Revoke
                                </button>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">
                            No staff invitations found.
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Help / info card -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-amber-800 mb-2">How invitations work</h4>
        <ul class="text-sm text-amber-700 list-disc list-inside space-y-1">
            <li>Invitations use a single-use, 256-bit token sent via email.</li>
            <li>Tokens expire after 7 days and can only be used once.</li>
            <li>Accepting an invitation creates a staff account with the selected level and permissions.</li>
            <li>Only owners can create, view, or revoke invitations.</li>
        </ul>
    </div>

</div>

<script>
    function revokeInvitation(event, id, email) {
        if (!confirm('Revoke invitation for ' + email + '?')) {
            return;
        }

        const btn = event.currentTarget;
        const originalText = btn.innerText;
        btn.innerText = 'Revoking&hellip;';
        btn.disabled = true;

        fetch("{{ route('admin.staff.invitations.destroy', 0) }}".replace('/0', '/' + id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (response) => {
                const result = await response.json();

                if (response.ok) {
                    btn.innerText = 'Revoked';
                    btn.classList.replace('text-red-600', 'text-green-600');
                    btn.classList.remove('hover:text-red-800');
                    btn.classList.add('hover:text-green-800');
                    btn.disabled = true;

                    // Remove the row after a short delay so the user sees feedback.
                    setTimeout(() => {
                        btn.closest('tr').remove();
                    }, 800);
                } else {
                    btn.innerText = originalText;
                    btn.disabled = false;
                    alert(result.message ?? 'Failed to revoke invitation. Please try again.');
                }
            })
            .catch(() => {
                btn.innerText = originalText;
                btn.disabled = false;
                alert('Network error. Please try again.');
            });
    }
</script>
