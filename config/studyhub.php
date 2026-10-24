<?php

/*
| Domain configuration for Abroad Study Hub.
|
| "fields" are the canonical meanings a list column can be mapped to. Only mapped
| columns of PUBLIC lists feed the public catalog; everything else stays in the list.
| "aliases" are matched (case/punctuation-insensitive) against spreadsheet headers on import.
*/

return [

    'categories' => [
        'german_uni' => ['label' => 'German universities', 'country' => 'Germany', 'icon' => '🇩🇪'],
        'us_uni' => ['label' => 'US universities', 'country' => 'USA', 'icon' => '🇺🇸'],
        'erasmus' => ['label' => 'Erasmus Mundus', 'country' => null, 'icon' => '🇪🇺'],
        'scholarship' => ['label' => 'Scholarships & funding', 'country' => null, 'icon' => '🎓'],
        'other' => ['label' => 'Other', 'country' => null, 'icon' => '📁'],
    ],

    'column_types' => [
        'text' => 'Text',
        'longtext' => 'Long text',
        'number' => 'Number',
        'date' => 'Date',
        'url' => 'Link',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
        'rating' => 'Rating (0–5)',
    ],

    'fields' => [
        'university' => ['label' => 'University / provider', 'aliases' => ['university', 'uni', 'universities', 'institution', 'school', 'college', 'host university', 'provider', 'university name']],
        'program' => ['label' => 'Program / scholarship name', 'aliases' => ['program', 'programme', 'course', 'program name', 'programme name', 'course name', 'study program', 'degree program', 'master program', 'subject', 'subjects', 'program/subject', 'subject/program', 'scholarship', 'scholarship name', 'title', 'name']],
        'country' => ['label' => 'Country', 'aliases' => ['country', 'destination']],
        'city' => ['label' => 'City / state', 'aliases' => ['city', 'location', 'state', 'campus']],
        'degree' => ['label' => 'Degree level', 'aliases' => ['degree', 'level', 'degree level', 'study level']],
        'language' => ['label' => 'Teaching language', 'aliases' => ['language', 'teaching language', 'language of instruction', 'medium']],
        'nc_status' => ['label' => 'NC / NC-free', 'aliases' => ['nc', 'nc status', 'nc free', 'nc/nc free', 'numerus clausus', 'admission restriction', 'restricted']],
        'intake' => ['label' => 'Intake', 'aliases' => ['intake', 'intakes', 'semester', 'start', 'start semester', 'session']],
        'deadline' => ['label' => 'Deadline / window', 'aliases' => ['deadline', 'deadlines', 'application deadline', 'application window', 'window', 'last date', 'apply by']],
        'tuition' => ['label' => 'Tuition fee', 'aliases' => ['tuition', 'tuition fee', 'tuition fees', 'fees', 'semester fee', 'cost']],
        'application_fee' => ['label' => 'Application fee', 'aliases' => ['application fee', 'app fee', 'app fees', 'application fees']],
        'ielts' => ['label' => 'IELTS', 'aliases' => ['ielts', 'ielts score', 'english requirement', 'english']],
        'toefl' => ['label' => 'TOEFL', 'aliases' => ['toefl', 'toefl ibt']],
        'gre' => ['label' => 'GRE / GMAT', 'aliases' => ['gre', 'gmat', 'gre/gmat', 'gre gmat', 'gre required']],
        'portal' => ['label' => 'Application portal (uni-assist / VPD)', 'aliases' => ['vpd', 'uni assist', 'uni-assist', 'portal', 'application portal', 'apply via', 'application method']],
        'entrance' => ['label' => 'Entrance exam / interview', 'aliases' => ['entrance', 'entrance exam', 'interview', 'entrance exam interview', 'test']],
        'ects' => ['label' => 'ECTS / credits', 'aliases' => ['ects', 'credits', 'credit requirement']],
        'gpa' => ['label' => 'Minimum CGPA', 'aliases' => ['cgpa', 'gpa', 'min cgpa', 'minimum cgpa', 'min gpa', 'minimum grade', 'grade']],
        'requirements' => ['label' => 'Requirements', 'aliases' => ['requirements', 'requirement', 'eligibility', 'admission requirements', 'program requirement', 'program requirements']],
        'documents' => ['label' => 'Required documents', 'aliases' => ['documents', 'required documents', 'documents required', 'docs']],
        'funding' => ['label' => 'Funding / stipend', 'aliases' => ['funding', 'stipend', 'scholarship amount', 'benefits', 'amount', 'financial aid', 'assistantship']],
        'website' => ['label' => 'Website', 'aliases' => ['website', 'link', 'url', 'program link', 'official link', 'web']],
    ],

    // Columns that are never published to the catalog even when their header looks canonical.
    'private_aliases' => ['remarks', 'notes', 'note', 'comments', 'red flag', 'status', 'fit', 'priority', 'my status'],

    'templates' => [
        'german_uni' => [
            ['University', 'text', 'university'],
            ['Program', 'text', 'program'],
            ['City', 'text', 'city'],
            ['Degree', 'select', 'degree', ['MSc', 'MA', 'MEng', 'MBA', 'BSc', 'PhD']],
            ['Language', 'select', 'language', ['English', 'German', 'English & German']],
            ['NC status', 'select', 'nc_status', ['NC-free', 'NC', 'Unknown']],
            ['Intake', 'select', 'intake', ['Winter', 'Summer', 'Winter & Summer']],
            ['Deadline', 'text', 'deadline'],
            ['Tuition fee', 'text', 'tuition'],
            ['Application fee', 'text', 'application_fee'],
            ['IELTS', 'number', 'ielts'],
            ['GRE/GMAT', 'select', 'gre', ['Not required', 'Optional', 'Required']],
            ['Portal', 'select', 'portal', ['uni-assist', 'VPD', 'Direct']],
            ['Entrance exam / interview', 'text', 'entrance'],
            ['Min CGPA', 'text', 'gpa'],
            ['Requirements', 'longtext', 'requirements'],
            ['Required documents', 'longtext', 'documents'],
            ['Website', 'url', 'website'],
            ['My status', 'select', null, ['Planning', 'Preparing', 'Applied', 'Accepted', 'Rejected']],
            ['Notes', 'longtext', null],
        ],
        'us_uni' => [
            ['University', 'text', 'university'],
            ['Program', 'text', 'program'],
            ['State / city', 'text', 'city'],
            ['Degree', 'select', 'degree', ['MS', 'PhD', 'MEng', 'MBA']],
            ['Intake', 'select', 'intake', ['Fall', 'Spring', 'Fall & Spring']],
            ['Deadline', 'text', 'deadline'],
            ['Application fee', 'text', 'application_fee'],
            ['Tuition fee', 'text', 'tuition'],
            ['Funding (TA/RA/fellowship)', 'longtext', 'funding'],
            ['GRE', 'select', 'gre', ['Not required', 'Optional', 'Required']],
            ['TOEFL', 'number', 'toefl'],
            ['IELTS', 'number', 'ielts'],
            ['Min GPA', 'text', 'gpa'],
            ['Requirements', 'longtext', 'requirements'],
            ['Required documents', 'longtext', 'documents'],
            ['Website', 'url', 'website'],
            ['My status', 'select', null, ['Planning', 'Preparing', 'Applied', 'Accepted', 'Rejected']],
            ['Notes', 'longtext', null],
        ],
        'erasmus' => [
            ['Programme', 'text', 'program'],
            ['Fit', 'rating', null],
            ['Application window', 'text', 'deadline'],
            ['Funding / stipend', 'text', 'funding'],
            ['IELTS', 'number', 'ielts'],
            ['Requirements', 'longtext', 'requirements'],
            ['Required documents', 'longtext', 'documents'],
            ['Website', 'url', 'website'],
            ['Remarks', 'longtext', null],
            ['Red flag', 'text', null],
            ['My status', 'select', null, ['Planning', 'Preparing', 'Applied', 'Selected', 'Reserve', 'Rejected']],
        ],
        'scholarship' => [
            ['Scholarship', 'text', 'program'],
            ['Provider', 'text', 'university'],
            ['Country', 'text', 'country'],
            ['Level', 'select', 'degree', ['Bachelor', 'Master', 'PhD', 'Any']],
            ['Deadline', 'text', 'deadline'],
            ['Funding / benefits', 'longtext', 'funding'],
            ['Eligibility', 'longtext', 'requirements'],
            ['Required documents', 'longtext', 'documents'],
            ['Website', 'url', 'website'],
            ['My status', 'select', null, ['Planning', 'Preparing', 'Applied', 'Awarded', 'Rejected']],
            ['Notes', 'longtext', null],
        ],
        'other' => [
            ['Name', 'text', 'program'],
            ['Notes', 'longtext', null],
        ],
    ],

    'import' => [
        'max_kb' => 10240,
        'max_rows' => 5000,
    ],
];
