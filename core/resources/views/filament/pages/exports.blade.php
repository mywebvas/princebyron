<div class="p-6 space-y-6">

    <div>
        <h3 class="text-lg font-medium text-gray-900">CSV Exports</h3>
        <p class="text-sm text-gray-500 mt-1">
            Export admin data as RFC 4180-compliant CSV with UTF-8 BOM for Excel.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach (['orders' => 'Orders', 'users' => 'Users', 'products' => 'Products', 'songs' => 'Songs'] as $type => $label)
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900">{{ $label }}</h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Export {{ strtolower($label) }} data as CSV.
                        </p>
                    </div>
                    <a
                        href="{{ route('admin.exports.' . $type) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Download CSV
                    </a>
                </div>
                <div class="mt-4 text-xs text-gray-400 border-t border-gray-100 pt-3">
                    <p>Streamed download — no server memory limit issues on large catalogs.</p>
                </div>
            </div>
        @endforeach

        <!-- Info card -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 col-span-full md:col-span-2">
            <h4 class="text-sm font-semibold text-gray-800 mb-2">Export details</h4>
            <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                <li>All exports are owner-only and rate-limited (10 per minute).</li>
                <li>Files include a UTF-8 BOM so Excel opens them with correct encoding.</li>
                <li>CSV follows RFC 4180: fields containing commas, quotes, or newlines are quoted; embedded quotes are doubled.</li>
                <li>Every export is recorded in the immutable audit trail.</li>
            </ul>
        </div>
    </div>

</div>
