@extends('layouts.admin')
@section('title', 'New questionnaire')
@section('body')
<main class="content questionnaire-create-shell">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">←&nbsp; Back to Questionnaire Management</a>

    <div class="questionnaire-create-heading">
        <h1>Create New Questionnaire</h1>
        <p class="lede">Set up a new questionnaire to assess student wellbeing. It will be saved as a draft until you publish it.</p>
    </div>

    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <section class="panel questionnaire-create-card">
        <form method="POST" action="{{ route('admin.questionnaires.store') }}" data-create-questionnaire>@csrf
            <section class="create-form-section">
                <div class="create-section-number">1</div>
                <div class="create-section-content">
                    <h2>Basic information</h2>
                    <p class="create-section-description">Enter the main details for your questionnaire.</p>
                    <div class="create-field">
                        <label for="title">Title <span class="required-mark">*</span></label>
                        <input id="title" name="title" value="{{ old('title') }}" placeholder="e.g. SheZen Wellbeing Questionnaire" required autofocus>
                        <small>Choose a clear and descriptive title.</small>
                    </div>
                    <div class="create-field">
                        <label for="description">Description <span class="muted">(optional)</span></label>
                        <textarea id="description" name="description" placeholder="One or two sentences explaining what this questionnaire is for. This description will be shown to students on the introduction screen.">{{ old('description') }}</textarea>
                        <small>Briefly describe the purpose and what students can expect.</small>
                    </div>
                </div>
            </section>

            <section class="create-form-section">
                <div class="create-section-number result-scale-number">2</div>
                <div class="create-section-content">
                    <h2>Questionnaire result scale</h2>
                    <p class="create-section-description">Choose the fixed score range that completed questionnaire results will be reported on.</p>
                    <div class="create-scale-fields">
                        <div class="create-field">
                            <label for="result_scale_min">Minimum score <span class="required-mark">*</span></label>
                            <input id="result_scale_min" name="result_scale_min" type="number" value="{{ old('result_scale_min', 0) }}" required aria-label="Scale minimum">
                        </div>
                        <span class="create-scale-to">to</span>
                        <div class="create-field">
                            <label for="result_scale_max">Maximum score <span class="required-mark">*</span></label>
                            <input id="result_scale_max" name="result_scale_max" type="number" value="{{ old('result_scale_max') }}" placeholder="e.g. 40" required aria-label="Scale maximum">
                        </div>
                    </div>
                    <div class="create-info-box create-info-blue"><i data-lucide="info"></i><div><strong>What does this mean?</strong><small>Answers may produce a different raw total, but the system automatically converts that raw score onto this fixed scale. For example, with a 0–40 scale, every completed assessment will receive a result between 0 and 40, even if questions are added or removed.</small></div></div>
                    <div class="create-info-box create-info-green"><i data-lucide="chart-no-axes-combined"></i><div><strong>You will define what the scores mean later</strong><small>In the Scoring step, you will set the score ranges and their meanings, for example: Low: 0–10 &nbsp; Moderate: 11–30 &nbsp; High: 31–40.</small></div></div>
                </div>
            </section>

            <section class="create-form-section create-date-section">
                <div class="create-section-number date-number">3</div>
                <div class="create-section-content">
                    <h2>Go-live date <span class="muted">(optional)</span></h2>
                    <p class="create-section-description">Pick a date and time now, or decide later. You can change this before publishing.</p>
                    <div class="create-field create-date-field">
                        <label for="published_at">Go-live date and time</label>
                        <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at') }}">
                        <small>If left blank, you can set the go-live date later.</small>
                    </div>
                </div>
            </section>

            <div class="create-draft-notice"><i data-lucide="file-check-2"></i><div><strong>This questionnaire will be created as a draft.</strong><small>Nothing will be visible to students until you complete the Review &amp; Publish step.</small></div></div>
            <div class="actions questionnaire-create-actions">
                <a class="button button-secondary" href="{{ route('admin.questionnaires.index') }}">Cancel</a>
                <button class="button" type="submit" data-create-submit>Create Draft</button>
            </div>
        </form>
    </section>
</main>
<style>
    .questionnaire-create-shell{max-width:920px;margin-top:25px}.questionnaire-create-heading{margin:8px 0 14px}.questionnaire-create-heading h1{margin:0 0 3px;color:#102b43;font-size:1.75rem;letter-spacing:-.035em}.questionnaire-create-heading p{font-size:.82rem;line-height:1.35;max-width:640px}.questionnaire-create-card{padding:10px 20px 16px;border-radius:14px}.create-form-section{display:grid;grid-template-columns:30px minmax(0,1fr);gap:12px;padding:13px 0 14px;border-bottom:1px solid #e2eaf2}.create-section-number{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:#dff0ff;color:#2a76bb;font-weight:700}.result-scale-number{background:#dff5eb;color:#198267}.date-number{background:#eee7ff;color:#7652b1}.create-section-content h2{margin:0 0 2px;color:#183b5c;font-size:1rem}.create-section-description{margin:0 0 11px;color:#60728b;font-size:.7rem}.create-field{margin-top:10px}.create-field label{margin-bottom:5px;color:#183b5c;font-size:.72rem}.create-field small,.create-info-box small,.create-draft-notice small{display:block;margin-top:4px;color:#687c91;font-size:.62rem;line-height:1.35}.create-field input,.create-field textarea{font-size:.72rem;border-color:#d2dfeb;border-radius:8px;padding:.58rem .7rem}.create-field textarea{min-height:74px}.create-field input:focus,.create-field textarea:focus{border-color:#2877be;box-shadow:0 0 0 3px rgba(40,119,190,.12)}.required-mark{color:#c84c5b}.create-scale-fields{display:grid;grid-template-columns:minmax(0,1fr) 28px minmax(0,1fr);align-items:end;gap:10px;max-width:610px}.create-scale-to{padding-bottom:10px;text-align:center;color:#60728b;font-size:.72rem}.create-info-box{display:flex;gap:10px;margin-top:9px;padding:9px 11px;border-radius:8px;font-size:.68rem;line-height:1.35}.create-info-box svg{width:17px;height:17px;flex:0 0 17px}.create-info-box strong{display:block;color:#245177;font-size:.68rem}.create-info-blue{background:#eaf4ff;color:#53718f}.create-info-blue svg{color:#2877be}.create-info-green{background:#eaf8f1;color:#537b6d}.create-info-green strong{color:#28745e}.create-info-green svg{color:#198267}.create-date-section{border-bottom:0;padding-bottom:13px}.create-date-field{max-width:360px}.create-draft-notice{display:flex;align-items:flex-start;gap:10px;margin-top:2px;padding:10px 11px;border-radius:8px;background:#eaf4ff;color:#285783}.create-draft-notice svg{width:18px;height:18px;flex:0 0 18px;color:#2877be}.create-draft-notice strong{display:block;font-size:.68rem}.create-draft-notice small{margin-top:2px}.questionnaire-create-actions{justify-content:flex-end;margin-top:10px}.questionnaire-create-card .button{min-height:36px;border-radius:8px;background:#2877be;box-shadow:0 4px 12px rgba(40,119,190,.17);font-size:.72rem}.questionnaire-create-card .button-secondary{background:#fff;color:#24476d;border:1px solid #d7e4f1;box-shadow:0 2px 5px rgba(45,83,130,.06)}
    @media(max-width:600px){.questionnaire-create-shell{margin-top:20px}.questionnaire-create-card{padding:8px 14px 14px}.create-scale-fields{grid-template-columns:1fr}.create-scale-to{display:none}.questionnaire-create-actions{justify-content:stretch}.questionnaire-create-actions>*{flex:1}.create-form-section{grid-template-columns:26px minmax(0,1fr);gap:9px}}
    @media(min-width:0px){.questionnaire-create-heading p{font-size:.9rem}.create-section-content h2{font-size:1.05rem}.create-section-description{font-size:.8rem}.create-field label{font-size:.82rem}.create-field small,.create-info-box small,.create-draft-notice small{font-size:.7rem}.create-field input,.create-field textarea{font-size:.85rem}.create-scale-to{font-size:.82rem}.create-info-box{font-size:.78rem}.create-info-box strong,.create-draft-notice strong{font-size:.78rem}.questionnaire-create-card .button{font-size:.82rem}}
</style>
<script>
document.querySelector('[data-create-questionnaire]')?.addEventListener('submit', function () {
    var button = this.querySelector('[data-create-submit]');
    if (button) { button.disabled = true; button.textContent = 'Creating…'; }
});
</script>
@endsection
