/// Static option lists shared by registration and profile editing so both
/// screens offer the same choices without either hardcoding its own copy.
class ReferenceData {
  const ReferenceData._();

  static const List<String> yearOfStudy = [
    'Year 1',
    'Year 2',
    'Year 3',
    'Year 4',
    'Postgraduate',
    'Other',
  ];

  /// Not restricted to Fiji or the Pacific — students may register from any
  /// country, so this list is intentionally broad.
  static const List<String> countries = [
    'Fiji',
    'Samoa',
    'Tonga',
    'Vanuatu',
    'Solomon Islands',
    'Papua New Guinea',
    'Kiribati',
    'Tuvalu',
    'Nauru',
    'Cook Islands',
    'Niue',
    'Australia',
    'New Zealand',
    'United States',
    'Canada',
    'United Kingdom',
    'Ireland',
    'India',
    'Pakistan',
    'Bangladesh',
    'Sri Lanka',
    'Nepal',
    'China',
    'Japan',
    'South Korea',
    'Philippines',
    'Indonesia',
    'Malaysia',
    'Singapore',
    'Thailand',
    'Vietnam',
    'South Africa',
    'Nigeria',
    'Kenya',
    'Ghana',
    'Egypt',
    'Germany',
    'France',
    'Italy',
    'Spain',
    'Portugal',
    'Netherlands',
    'Sweden',
    'Norway',
    'Brazil',
    'Mexico',
    'Argentina',
    'Chile',
    'Other',
  ];
}
