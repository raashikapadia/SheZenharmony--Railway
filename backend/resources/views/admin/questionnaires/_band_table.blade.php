@php($bands = collect($bands)->sortBy('min_score')->values())
@if($bands->isEmpty())
    <p class="muted">No ranges configured.</p>
@else
    <div class="table-wrap"><table>
        <thead><tr><th>Range</th><th>Label</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($bands as $band)
            <tr>
                <td>{{ $band->min_score }}–{{ $band->max_score }}</td>
                <td>{{ $band->label }}</td>
                <td><span class="badge {{ $band->is_active ? 'active' : '' }}">{{ $band->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @php($active = $bands->where('is_active', true)->sortBy('min_score')->values())
    @php($floor = $floor ?? 0)
    @php($covered = $active->isNotEmpty() && $active->first()->min_score <= $floor && $active->last()->max_score >= $ceiling)
    <p class="muted" style="margin-top:.5rem">
        @if($covered)Covers {{ $floor }}–{{ $ceiling }}.@else <span class="error">Does not fully cover {{ $floor }}–{{ $ceiling }}.</span>@endif
    </p>
@endif
