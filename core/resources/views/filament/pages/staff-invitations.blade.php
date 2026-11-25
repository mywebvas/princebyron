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
                                <form method="POST" action="{{ route('admin.staff.invitations.destroy', $invitation['id']) }}" class="inline" onsubmit="return confirm('Revoke this invitation?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-xs font-medium text-red-600 hover:text-red-800 underline">
                                        Revoke
                                    </button>
                                </form>
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
