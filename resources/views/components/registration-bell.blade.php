@props(['pending', 'registrations'])

{{-- Admin notification bell: new registrations, shared by every admin. --}}
<details class="relative" data-dropdown>
    <summary class="list-none cursor-pointer relative grid place-items-center w-9 h-9 rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50" aria-label="Registrations: {{ $pending }} waiting for approval">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
        @if ($pending > 0)
            <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 grid place-items-center rounded-full bg-rose-600 text-[11px] font-bold text-white">{{ $pending > 99 ? '99+' : $pending }}</span>
        @endif
    </summary>
    <div class="fixed inset-x-4 top-16 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-88 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl z-50">
        <div class="flex items-center justify-between px-3 pt-1 pb-2 border-b border-slate-100">
            <span class="text-sm font-semibold text-slate-900">New registrations</span>
            <span class="text-xs text-slate-500">{{ $pending }} waiting</span>
        </div>
        <ul class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            @forelse ($registrations as $student)
                <li class="flex items-start gap-3 px-3 py-2.5">
                    <x-avatar :user="$student" class="w-9 h-9 text-sm" />
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.students.show', $student) }}" class="block break-words text-sm font-semibold text-slate-900 hover:underline">{{ $student->name }}</a>
                        <div class="text-xs text-slate-500">SSC {{ $student->ssc_batch }} · registered {{ $student->created_at->diffForHumans() }}</div>
                        @if ($student->status === 'pending')
                            <form method="POST" action="{{ route('admin.students.status', $student) }}" class="mt-1.5">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="approved">
                                <button class="rounded-lg bg-green-600 px-3 py-1 text-xs font-semibold text-white hover:bg-green-700">Approve</button>
                            </form>
                        @elseif ($student->status === 'approved')
                            <div class="mt-1 text-xs font-medium text-green-700">✅ Approved{{ $student->reviewer ? ' by '.$student->reviewer->name : '' }}{{ $student->reviewed_at ? ' · '.$student->reviewed_at->diffForHumans() : '' }}</div>
                        @else
                            <div class="mt-1 text-xs font-medium text-rose-700">✖ Rejected{{ $student->reviewer ? ' by '.$student->reviewer->name : '' }}{{ $student->reviewed_at ? ' · '.$student->reviewed_at->diffForHumans() : '' }}</div>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-slate-500">No registrations yet.</li>
            @endforelse
        </ul>
        <a href="{{ route('admin.students.index', ['status' => 'pending']) }}" class="mt-1 block rounded-xl px-3 py-2 text-center text-sm font-medium text-brand-700 hover:bg-brand-50">View all pending</a>
    </div>
</details>
