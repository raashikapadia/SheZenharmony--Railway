<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\StressQuestion;
use App\Services\ScaleBandValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminQuestionnaireController extends Controller
{
    public function index(): View
    {
        return view('admin.questionnaires.index', ['questionnaires' => Questionnaire::query()->withCount(['questions', 'scoreBands'])->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return $this->form(new Questionnaire);
    }

    public function store(Request $request, ScaleBandValidator $bandValidator): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $data, $bandValidator): void {
            $questionnaire = Questionnaire::query()->create($data['questionnaire'] + ['created_by_user_id' => $request->user()->id]);
            $this->syncConfiguration($questionnaire, $data, $bandValidator);
        });

        return redirect()->route('admin.questionnaires.index')->with('status', 'Questionnaire created.');
    }

    public function edit(Questionnaire $questionnaire): View
    {
        return $this->form($questionnaire->load(['questions', 'scoreBands']));
    }

    public function update(Request $request, Questionnaire $questionnaire, ScaleBandValidator $bandValidator): RedirectResponse
    {
        $data = $this->validated($request, $questionnaire);
        DB::transaction(function () use ($questionnaire, $data, $bandValidator): void {
            $questionnaire->update($data['questionnaire']);
            $this->syncConfiguration($questionnaire, $data, $bandValidator);
        });

        return redirect()->route('admin.questionnaires.index')->with('status', 'Questionnaire updated.');
    }

    public function destroy(Questionnaire $questionnaire): RedirectResponse
    {
        $questionnaire->update(['status' => 'archived', 'is_active' => false]);

        return back()->with('status', 'Questionnaire archived.');
    }

    private function form(Questionnaire $questionnaire): View
    {
        return view('admin.questionnaires.form', [
            'questionnaire' => $questionnaire,
            'availableQuestions' => StressQuestion::query()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    private function validated(Request $request, ?Questionnaire $questionnaire = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['stress'])],
            'version' => ['required', 'integer', 'min:1', Rule::unique('questionnaires')->where(fn ($query) => $query->where('type', $request->input('type')))->ignore($questionnaire)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['required', 'integer', 'distinct', 'exists:stress_questions,id'],
            'questions.*.position' => ['required', 'integer', 'min:0'],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'bands' => ['nullable', 'array'],
            'bands.*.id' => ['nullable', 'integer'],
            'bands.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'bands.*.min_score' => ['required', 'integer'],
            'bands.*.max_score' => ['required', 'integer'],
            'bands.*.position' => ['required', 'integer', 'min:0'],
            'bands.*.is_active' => ['nullable', 'boolean'],
        ]);

        $data['questionnaire'] = collect($data)->only(['title', 'description', 'type', 'version', 'status', 'published_at'])->all() + [
            'is_active' => $request->boolean('is_active'),
        ];

        return $data;
    }

    private function syncConfiguration(Questionnaire $questionnaire, array $data, ScaleBandValidator $validator): void
    {
        $questions = collect($data['questions'] ?? [])->mapWithKeys(fn ($item) => [
            $item['id'] => ['position' => $item['position'], 'is_required' => (bool) ($item['is_required'] ?? false)],
        ])->all();
        $questionnaire->questions()->sync($questions);

        $validator->validateCollection($data['bands'] ?? []);
        $retained = [];
        foreach ($data['bands'] ?? [] as $item) {
            $band = isset($item['id']) ? $questionnaire->scoreBands()->whereKey($item['id'])->firstOrFail() : null;
            unset($item['id']);
            $item['is_active'] = (bool) ($item['is_active'] ?? false);
            $band ??= $questionnaire->scoreBands()->make();
            $band->fill($item + ['created_by_user_id' => $questionnaire->created_by_user_id])->save();
            $retained[] = $band->id;
        }
        $questionnaire->scoreBands()->whereNotIn('id', $retained)->update(['is_active' => false]);
    }
}
