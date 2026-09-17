class StudentProfile {
  const StudentProfile({
    required this.shezenId,
    this.dateOfBirth,
    this.age,
    this.country,
    this.yearOfStudy,
    this.yearOfStudyDetail,
    this.employmentStatus,
    this.relationshipStatus,
    this.hasChildren,
    this.livingSituation,
  });

  final String shezenId;

  /// ISO `yyyy-MM-dd`, or null if not yet set.
  final String? dateOfBirth;

  /// Always computed server-side from [dateOfBirth] — never edited directly.
  final int? age;
  final String? country;
  final String? yearOfStudy;

  /// What the student typed when [yearOfStudy] is "Other"; null otherwise.
  final String? yearOfStudyDetail;
  final String? employmentStatus;
  final String? relationshipStatus;
  final bool? hasChildren;
  final String? livingSituation;

  /// Year of study as it should read on screen, e.g. "Other (Foundation)".
  String? get yearOfStudyDescription {
    final detail = yearOfStudyDetail;
    if (yearOfStudy == 'Other' && detail != null && detail.isNotEmpty) {
      return 'Other ($detail)';
    }
    return yearOfStudy;
  }

  factory StudentProfile.fromJson(Map<String, dynamic> json) => StudentProfile(
    shezenId: json['shezen_id'] as String? ?? '',
    dateOfBirth: json['date_of_birth'] as String?,
    age: json['age'] as int?,
    country: json['country'] as String?,
    yearOfStudy: json['year_of_study'] as String?,
    yearOfStudyDetail: json['year_of_study_detail'] as String?,
    employmentStatus: json['employment_status'] as String?,
    relationshipStatus: json['relationship_status'] as String?,
    hasChildren: json['has_children'] as bool?,
    livingSituation: json['living_situation'] as String?,
  );
}
