{{-- Kept for existing includes: the step strip is one component now, so
     every screen shows the same five steps. --}}
@include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'review' => $review ?? null, 'step' => $step ?? 1])
