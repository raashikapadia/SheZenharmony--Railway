class StudentProfile {
  const StudentProfile({
    required this.shezenId,
    this.dateOfBirth,
    this.age,
    this.country,
    this.yearOfStudy,
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
  final String? employmentStatus;
  final String? relationshipStatus;
  final bool? hasChildren;
  final String? livingSituation;

  factory StudentProfile.fromJson(Map<String, dynamic> json) => StudentProfile(
    shezenId: json['shezen_id'] as String? ?? '',
    dateOfBirth: json['date_of_birth'] as String?,
    age: json['age'] as int?,
    country: json['country'] as String?,
    yearOfStudy: json['year_of_study'] as String?,
    employmentStatus: json['employment_status'] as String?,
    relationshipStatus: json['relationship_status'] as String?,
    hasChildren: json['has_children'] as bool?,
    livingSituation: json['living_situation'] as String?,
  );
}
