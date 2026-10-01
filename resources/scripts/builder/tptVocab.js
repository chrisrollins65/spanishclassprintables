/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/tptVocab.js (commit 34674dd) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
// The TpT upload form's controlled vocabularies, read off the live form on
// 2026-08-31. Every one of these fields is a react-select over a FIXED list —
// nothing is free text — so anything the AI invents silently fails to attach.
// Generating listings against these lists is what makes the autofill land.

// Max selections the form enforces per field.
const LIMITS = {
  // The form says "Select up to four grades", but that is advisory copy - the
  // store's own listings carry six, and adding a seventh on the edit form was
  // accepted. Seven is what every band below is sized to.
  gradeLevels: 7,
  subjectAreas: 3,
  tags: 6,
  formats: 3,
  customCategories: 3,
  // Hard cap: the Title field stops accepting input at 80, so a longer title
  // would be silently cut off mid-word in the form.
  titleChars: 80,
};

// Grade Level is a row of checkboxes, matched by their label text.
const GRADE_LEVELS = [
  'Preschool', 'Kindergarten', '1st Grade', '2nd Grade', '3rd Grade',
  '4th Grade', '5th Grade', '6th Grade', '7th Grade', '8th Grade',
  '9th Grade', '10th Grade', '11th Grade', '12th Grade',
  'Higher Education', 'Adult Education', 'Not Grade Specific',
];

// Grades are stamped on the listing rather than chosen per packet - the AI used
// to drift older or narrower on packets whose vocabulary looked harder, which
// only ever cost the listing search traffic. What varies is the KIND of
// product, not the topic, so the band belongs to the kind.
//
// This is the worksheet band, and the fallback for a kind without one.
const STORE_GRADE_LEVELS = [
  '2nd Grade', '3rd Grade', '4th Grade', '5th Grade', '6th Grade',
  '7th Grade', '8th Grade',
];

/* The games sit higher than the worksheets, and the quiz higher than bingo.
 *
 * "Beginner Spanish" is a level, not an age. The quiz board's cheapest row is
 * English-to-Spanish translation of concrete nouns, which is as much a high
 * school Spanish 1 class as a fifth grade FLES one, so raising the top of the
 * band costs nothing in fit and reaches buyers a word search never will.
 *
 * The bottom is where the real mismatch was. A bingo player has the word
 * printed in front of them and only has to recognise it; a quiz team hears a
 * whole Spanish sentence, with no text and no list, and has to produce the
 * word. That is two different asks of the same vocabulary, so they get two
 * different floors - and 2nd grade, which used to be on every listing, is
 * under both of them.
 *
 * Nothing goes past 10th: a Spanish 3 or 4 teacher who buys a board of
 * concrete nouns has been mis-sold, and says so in the review.
 */
const KIND_GRADE_LEVELS = {
  bingo: [
    '3rd Grade', '4th Grade', '5th Grade', '6th Grade', '7th Grade',
    '8th Grade', '9th Grade',
  ],
  quiz: [
    '4th Grade', '5th Grade', '6th Grade', '7th Grade', '8th Grade',
    '9th Grade', '10th Grade',
  ],
};

// Subject Area, World Languages group plus the catch-alls. The full taxonomy
// runs to 132 entries across 8 groups, but a Spanish store never leaves these.
// Every listing in this store pairs "Spanish" with "Vocabulary".
const SUBJECT_AREAS = [
  'Spanish', 'Vocabulary', 'French', 'German', 'Italian', 'Portuguese', 'Latin',
  'American Sign Language', 'Arabic', 'Chinese', 'Hebrew', 'Japanese',
  'Russian', 'Gaeilge', 'Other (World Language)',
  'For All Subjects', 'Not Subject Specific',
];

// "Format" is the FILE format, not the resource type. For a printable packet
// the answer is always PDF.
const FORMATS = [
  'PDF', 'Digital', 'Easel', 'Easel Activities', 'Easel Assessments',
  'Google Apps', 'Boom Cards', 'Canva', 'Seesaw', 'Image', 'Video', 'Audio',
  'eBook', 'Fonts', 'Microsoft Word', 'Microsoft PowerPoint',
  'Microsoft Excel', 'Microsoft Publisher', 'Microsoft OneDrive',
  'Interactive Whiteboards', 'Prezi', 'Other (Digital)',
];

// The single "Tag" field carries audience, language, resource type and theme.
// Group headers are unselectable, so they are not listed here.
const TAGS = {
  Audience: ['Homeschool', 'Parents', 'Staff & Administrators', 'TPT Sellers'],
  Language: ['En español', 'En français', 'English (UK)'],
  Programs: [
    'Advanced Placement (AP)', 'Early Intervention',
    'GATE / Gifted and Talented', 'International Baccalaureate (IB)',
    'Montessori',
  ],
  'Classroom Decor': ['Bulletin Board Ideas', 'Posters', 'Word Walls', 'Clip Art'],
  Forms: [
    'Classroom Forms', 'Elective Course Proposals', 'Grant Proposals',
    'Professional Documents', 'School Nurse Documents', 'Student Council',
  ],
  'Hands-on Activities': [
    'Activities', 'Bell Ringers', 'Centers', 'Cultural Activities', 'DBQs',
    'Escape Rooms', 'Games', 'Internet Activities', 'Laboratory',
    'Literature Circles', 'Project-based Learning', 'Projects', 'Research',
    'Scripts', 'Simulations', 'Songs', 'Webquests',
  ],
  Instruction: [
    'Bibliographies', 'Guided Reading Books', 'Handouts',
    'Interactive Notebooks', 'Scaffolded Notes', 'Printables',
  ],
  'Student Assessment': [
    'Assessment', 'Critical Thinking and Problem Solving', 'Study Guides',
    'Study Skills', 'Test Preparation',
  ],
  'Student Practice': [
    'Flash Cards', 'Graphic Organizers', 'Homework',
    'Independent Work Packet', 'Movie Guides', 'Task Cards', 'Workbooks',
    'Worksheets',
  ],
  'Teacher Tools': [
    'Awards and Certificates', 'Classroom Management', 'Homeschool Curricula',
    'Leadership Lessons', 'Lectures', 'Lessons', 'Outlines',
    'Reflective Journals for Teachers', 'Rubrics', 'Syllabi',
    'Teacher Manuals', 'Teacher Planners', 'Thematic Unit Plans',
    'Tools for Common Core', 'Tools for Sellers', 'Unit Plans',
    'Yearlong Curriculum',
  ],
  Supports: ['ESL, EFL, and ELL'],
  'Special Education': [
    'Applied Behavior Analysis', 'Data', 'Life Skills', 'Neurodiversity',
    'Screenings and Assessments', 'Social Skills', 'Visual Supports',
    'Other (Special education)',
  ],
  Specialty: [
    'Career and Technical Education', 'Child Care', 'Coaching', 'Cooking',
    'Leadership', 'Occupational Therapy', 'Physical Therapy',
    'Professional Development', 'Service Learning', 'Vocational Education',
    'Other (Specialty)',
  ],
  'Speech Therapy': [
    'AAC', 'Fluency and Stuttering', 'Language', 'Speech Articulation',
    'Voice', 'Other (Speech therapy)',
  ],
  Holiday: [
    'AAPI History Month', "April Fools' Day", 'Arbor Day',
    'Black History Month', 'Christmas-Chanukah-Kwanzaa', 'Cinco de Mayo',
    'Day of the Dead / Dia de los Muertos', 'Diwali', 'Earth Day', 'Easter',
    "Father's Day", 'Groundhog Day', 'Halloween', 'Hispanic Heritage Month',
    'July 4/Independence Day', 'Juneteenth', 'Labor Day', 'Lunar New Year',
    'Mardi Gras', 'Martin Luther King Day', 'Memorial Day', "Mother's Day",
    'New Year', 'Passover', "Presidents' Day", 'Ramadan',
    "St. Patrick's Day", 'Thanksgiving', "Valentine's Day", 'Veterans Day',
    "Women's History Month",
  ],
  Seasonal: ['Autumn', 'Back to School', 'End of Year', 'Spring', 'Summer', 'Winter'],
};

// This seller's own store sections, read off the Custom Category field. They
// are per-account, so if the store's sections change, reopen the upload form,
// click Custom Category, and copy the list again.
const CUSTOM_CATEGORIES = [
  'La familia 👨‍👩‍👧‍👦',
  'Festividades 🎉',
  'Las estaciones del año 🍂',
  'Los animales 🐸',
  'Decoración de clase 👩‍🏫',
];

// The store's section names carry an emoji; the Trello board's labels do not,
// and the model drops it about half the time. Compare the way the autofill's
// option matcher does - letters and digits only, so the emoji and the accents
// both fall away - and answer with the store's exact string, which is what the
// upload form actually needs.
// Accents are folded rather than blanked, so "Las estaciones del ano" typed
// without the tilde still matches "Las estaciones del año 🍂".
const categoryKey = s => String(s || '')
  .normalize('NFD')
  .replace(/[̀-ͯ]/g, '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ')
  .trim();

/* Store sections for a KIND of product, stamped on every pack of that kind.
 *
 * Kept out of CUSTOM_CATEGORIES on purpose: that list is the menu the listing
 * model picks a THEME section from, and a sports worksheet must never be
 * offered "Bingo". These are not chosen — every bingo pack is in the bingo
 * section — so they never reach a prompt.
 *
 * They must match the sections created on TpT, emoji aside. A name here with no
 * section behind it shows up in the fill report as "not matched".
 */
const KIND_CATEGORIES = {
  bingo: '🎲 Bingo',
  quiz: '🏆 Jeopardy Style',
};

// A game runs one class period, not the worksheet packet's 90 minutes. Must be
// one of TEACHING_DURATIONS, which is TpT's own menu.
const GAME_DETAILS = { teachingDuration: '45 Minutes' };

function matchCustomCategory(name) {
  const key = categoryKey(name);
  if (!key) return null;
  return [...CUSTOM_CATEGORIES, ...Object.values(KIND_CATEGORIES)]
    .find(c => categoryKey(c) === key) || null;
}

const ALL_TAGS = Object.values(TAGS).flat();

// The Details section's two dropdowns. Every listing in this store sets the
// same pair, so there is nothing for the model to decide - a packet always
// ships its answer key, and 90 minutes is what the store quotes for five pages.
const DETAILS = {
  answerKey: 'Included',
  teachingDuration: '90 Minutes',
  // Required before TpT will collect sales tax. This is the code the store has
  // used on every listing; it is a tax classification, so change it only on
  // advice, not because another option sounds like a closer description.
  taxCode: 'Digital Images - Streaming / Electronic Download',
};

// What TpT offers in those two menus, for when a packet wants something else.
const ANSWER_KEY_OPTIONS = [
  'N/A', 'Included', 'Not Included', 'Included with Rubric', 'Rubric Only',
  'Does Not Apply',
];
const TAX_CODES = [
  'Digital audio works sold to an end user with rights for permanent use',
  'Digital books sold to an end user with rights for permanent use',
  'Digital Images - Streaming / Electronic Download',
  'Videos - Streaming / Electronic Download',
  'Other Digital Goods - No Physical Media',
];
const TEACHING_DURATIONS = [
  'N/A', '30 Minutes', '40 Minutes', '45 Minutes', '50 Minutes', '55 Minutes',
  '1 Hour', '90 Minutes', '2 Hours', '3 Hours', '2 Days', '3 Days', '4 Days',
  '1 Week', '2 Weeks', '3 Weeks', '1 Month', '2 Months', '3 Months',
  '1 Semester', '1 Year', 'Lifelong Tool', 'Other',
];

module.exports = {
  LIMITS, GRADE_LEVELS, STORE_GRADE_LEVELS, KIND_GRADE_LEVELS, SUBJECT_AREAS,
  FORMATS, TAGS, ALL_TAGS,
  CUSTOM_CATEGORIES, DETAILS, ANSWER_KEY_OPTIONS, TEACHING_DURATIONS, TAX_CODES,
  KIND_CATEGORIES, GAME_DETAILS, matchCustomCategory,
};
