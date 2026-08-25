/// A ready-made set of answer options (label + score) an admin can apply to
/// a question in one tap instead of typing every option by hand. Purely a
/// Flutter-side authoring convenience — nothing here is sent to the backend
/// as-is; applying a template just pre-fills the same option fields the
/// admin could otherwise fill in manually, and every value stays fully
/// editable afterwards.
class ScaleOption {
  const ScaleOption({required this.label, required this.value, required this.score});

  final String label;
  final String value;
  final int score;
}

class ScaleTemplate {
  const ScaleTemplate({required this.id, required this.name, required this.options});

  final String id;
  final String name;
  final List<ScaleOption> options;
}

class ScaleTemplates {
  ScaleTemplates._();

  static const frequency5 = ScaleTemplate(
    id: 'frequency_5',
    name: 'Frequency — Never to Very Often (5-point)',
    options: [
      ScaleOption(label: 'Never', value: 'never', score: 0),
      ScaleOption(label: 'Rarely', value: 'rarely', score: 1),
      ScaleOption(label: 'Sometimes', value: 'sometimes', score: 2),
      ScaleOption(label: 'Often', value: 'often', score: 3),
      ScaleOption(label: 'Very Often', value: 'very_often', score: 4),
    ],
  );

  static const agreement5 = ScaleTemplate(
    id: 'agreement_5',
    name: 'Agreement — Strongly Disagree to Strongly Agree (5-point)',
    options: [
      ScaleOption(label: 'Strongly Disagree', value: 'strongly_disagree', score: 0),
      ScaleOption(label: 'Disagree', value: 'disagree', score: 1),
      ScaleOption(label: 'Neutral', value: 'neutral', score: 2),
      ScaleOption(label: 'Agree', value: 'agree', score: 3),
      ScaleOption(label: 'Strongly Agree', value: 'strongly_agree', score: 4),
    ],
  );

  static const frequency3 = ScaleTemplate(
    id: 'frequency_3',
    name: 'Frequency — Rarely to Often (3-point)',
    options: [
      ScaleOption(label: 'Rarely', value: 'rarely', score: 0),
      ScaleOption(label: 'Sometimes', value: 'sometimes', score: 1),
      ScaleOption(label: 'Often', value: 'often', score: 2),
    ],
  );

  static const yesNo = ScaleTemplate(
    id: 'yes_no',
    name: 'Yes / No',
    options: [
      ScaleOption(label: 'No', value: 'no', score: 0),
      ScaleOption(label: 'Yes', value: 'yes', score: 1),
    ],
  );

  static const all = [frequency5, agreement5, frequency3, yesNo];

  static const defaultTemplate = frequency5;
}
