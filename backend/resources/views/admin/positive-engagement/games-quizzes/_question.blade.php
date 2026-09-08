@php($value = fn (string $key, $default = '') => old("questions.$index.$key", $question[$key] ?? $default))
<div class="editor-row" data-question>
    @if(!empty($question['id']))<input type="hidden" name="questions[{{ $index }}][id]" value="{{ $question['id'] }}">@endif
    <div class="fld grow"><label>Question {{ ((int) $index) + 1 }}</label><textarea name="questions[{{ $index }}][question_text]" required>{{ $value('question_text') }}</textarea></div>
    <div class="fld"><label>Option A</label><input name="questions[{{ $index }}][option_a]" type="text" value="{{ $value('option_a') }}" required></div>
    <div class="fld"><label>Option B</label><input name="questions[{{ $index }}][option_b]" type="text" value="{{ $value('option_b') }}" required></div>
    <div class="fld"><label>Option C</label><input name="questions[{{ $index }}][option_c]" type="text" value="{{ $value('option_c') }}" required></div>
    <div class="fld"><label>Option D</label><input name="questions[{{ $index }}][option_d]" type="text" value="{{ $value('option_d') }}" required></div>
    <div class="fld narrow"><label>Correct</label><select name="questions[{{ $index }}][correct_option]" required>@foreach(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $label)<option value="{{ $key }}" @selected($value('correct_option', 'a') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="fld grow"><label>Explanation (optional)</label><input name="questions[{{ $index }}][explanation]" type="text" value="{{ $value('explanation') }}"></div>
    <button class="button button-danger row-remove" type="button" data-remove-question>Remove</button>
</div>
