<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Polje :attribute mora biti prihvaćeno.',
    'accepted_if' => 'Polje :attribute mora biti prihvaćeno kada je :other :value.',
    'active_url' => 'Polje :attribute nije valjan URL.',
    'after' => 'Polje :attribute mora biti datum nakon :date.',
    'after_or_equal' => 'Polje :attribute mora biti datum nakon ili jednak :date.',
    'alpha' => 'Polje :attribute može sadržavati samo slova.',
    'alpha_dash' => 'Polje :attribute može sadržavati samo slova, brojeve, crtice i donje crte.',
    'alpha_num' => 'Polje :attribute može sadržavati samo slova i brojeve.',
    'array' => 'Polje :attribute mora biti niz.',
    'before' => 'Polje :attribute mora biti datum prije :date.',
    'before_or_equal' => 'Polje :attribute mora biti datum prije ili jednak :date.',
    'between' => [
        'numeric' => 'Polje :attribute mora biti između :min i :max.',
        'file' => 'Polje :attribute mora biti između :min i :max kilobajta.',
        'string' => 'Polje :attribute mora biti između :min i :max karaktera.',
        'array' => 'Polje :attribute mora imati između :min i :max stavki.',
    ],
    'boolean' => 'Polje :attribute mora biti tačno ili netačno.',
    'confirmed' => 'Potvrda polja :attribute se ne poklapa.',
    'current_password' => 'Lozinka nije ispravna.',
    'date' => 'Polje :attribute nije valjan datum.',
    'date_equals' => 'Polje :attribute mora biti datum jednak :date.',
    'date_format' => 'Polje :attribute se ne poklapa sa formatom :format.',
    'declined' => 'Polje :attribute mora biti odbačeno.',
    'declined_if' => 'Polje :attribute mora biti odbačeno kada je :other :value.',
    'different' => 'Polja :attribute i :other moraju biti različita.',
    'digits' => 'Polje :attribute mora imati :digits cifara.',
    'digits_between' => 'Polje :attribute mora imati između :min i :max cifara.',
    'dimensions' => 'Polje :attribute ima nevaljane dimenzije slike.',
    'distinct' => 'Polje :attribute ima duplu vrijednost.',
    'email' => 'Polje :attribute mora biti valjana email adresa.',
    'ends_with' => 'Polje :attribute mora završavati sa jednim od sljedećih: :values.',
    'enum' => 'Odabrano :attribute nije valjano.',
    'exists' => 'Odabrano :attribute nije valjano.',
    'file' => 'Polje :attribute mora biti fajl.',
    'filled' => 'Polje :attribute mora imati vrijednost.',
    'gt' => [
        'numeric' => 'Polje :attribute mora biti veće od :value.',
        'file' => 'Polje :attribute mora biti veće od :value kilobajta.',
        'string' => 'Polje :attribute mora biti veće od :value karaktera.',
        'array' => 'Polje :attribute mora imati više od :value stavki.',
    ],
    'gte' => [
        'numeric' => 'Polje :attribute mora biti veće ili jednako :value.',
        'file' => 'Polje :attribute mora biti veće ili jednako :value kilobajta.',
        'string' => 'Polje :attribute mora biti veće ili jednako :value karaktera.',
        'array' => 'Polje :attribute mora imati :value stavki ili više.',
    ],
    'image' => 'Polje :attribute mora biti slika.',
    'in' => 'Odabrano :attribute nije valjano.',
    'in_array' => 'Polje :attribute ne postoji u :other.',
    'integer' => 'Polje :attribute mora biti cijeli broj.',
    'ip' => 'Polje :attribute mora biti valjana IP adresa.',
    'ipv4' => 'Polje :attribute mora biti valjana IPv4 adresa.',
    'ipv6' => 'Polje :attribute mora biti valjana IPv6 adresa.',
    'json' => 'Polje :attribute mora biti valjan JSON string.',
    'lt' => [
        'numeric' => 'Polje :attribute mora biti manje od :value.',
        'file' => 'Polje :attribute mora biti manje od :value kilobajta.',
        'string' => 'Polje :attribute mora biti manje od :value karaktera.',
        'array' => 'Polje :attribute mora imati manje od :value stavki.',
    ],
    'lte' => [
        'numeric' => 'Polje :attribute mora biti manje ili jednako :value.',
        'file' => 'Polje :attribute mora biti manje ili jednako :value kilobajta.',
        'string' => 'Polje :attribute mora biti manje ili jednako :value karaktera.',
        'array' => 'Polje :attribute ne smije imati više od :value stavki.',
    ],
    'mac_address' => 'Polje :attribute mora biti valjana MAC adresa.',
    'max' => [
        'numeric' => 'Polje :attribute ne smije biti veće od :max.',
        'file' => 'Polje :attribute ne smije biti veće od :max kilobajta.',
        'string' => 'Polje :attribute ne smije biti veće od :max karaktera.',
        'array' => 'Polje :attribute ne smije imati više od :max stavki.',
    ],
    'mimes' => 'Polje :attribute mora biti fajl tipa: :values.',
    'mimetypes' => 'Polje :attribute mora biti fajl tipa: :values.',
    'min' => [
        'numeric' => 'Polje :attribute mora biti najmanje :min.',
        'file' => 'Polje :attribute mora biti najmanje :min kilobajta.',
        'string' => 'Polje :attribute mora biti najmanje :min karaktera.',
        'array' => 'Polje :attribute mora imati najmanje :min stavki.',
    ],
    'multiple_of' => 'Polje :attribute mora biti višekratnik od :value.',
    'not_in' => 'Odabrano :attribute nije valjano.',
    'not_regex' => 'Format polja :attribute nije valjan.',
    'numeric' => 'Polje :attribute mora biti broj.',
    'password' => 'Lozinka nije ispravna.',
    'present' => 'Polje :attribute mora biti prisutno.',
    'prohibited' => 'Polje :attribute je zabranjeno.',
    'prohibited_if' => 'Polje :attribute je zabranjeno kada je :other :value.',
    'prohibited_unless' => 'Polje :attribute je zabranjeno osim ako :other nije u :values.',
    'prohibits' => 'Polje :attribute zabranjuje :other da bude prisutno.',
    'regex' => 'Format polja :attribute nije valjan.',
    'required' => 'Polje :attribute je obavezno.',
    'required_array_keys' => 'Polje :attribute mora sadržavati stavke za: :values.',
    'required_if' => 'Polje :attribute je obavezno kada je :other :value.',
    'required_unless' => 'Polje :attribute je obavezno osim ako :other nije u :values.',
    'required_with' => 'Polje :attribute je obavezno kada je :values prisutno.',
    'required_with_all' => 'Polje :attribute je obavezno kada su :values prisutni.',
    'required_without' => 'Polje :attribute je obavezno kada :values nije prisutno.',
    'required_without_all' => 'Polje :attribute je obavezno kada nijedan od :values nije prisutan.',
    'same' => 'Polja :attribute i :other se moraju poklapati.',
    'size' => [
        'numeric' => 'Polje :attribute mora biti :size.',
        'file' => 'Polje :attribute mora biti :size kilobajta.',
        'string' => 'Polje :attribute mora biti :size karaktera.',
        'array' => 'Polje :attribute mora sadržavati :size stavki.',
    ],
    'starts_with' => 'Polje :attribute mora počinjati sa jednim od sljedećih: :values.',
    'string' => 'Polje :attribute mora biti string.',
    'timezone' => 'Polje :attribute mora biti valjana zona.',
    'unique' => 'Polje :attribute je već zauzeto.',
    'uploaded' => 'Polje :attribute nije uspješno učitano.',
    'url' => 'Polje :attribute mora biti valjan URL.',
    'uuid' => 'Polje :attribute mora biti valjan UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "rule.attribute" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'email' => 'email',
        'username' => 'korisničko ime',
        'password' => 'lozinka',
        'name' => 'ime i prezime',
        'remember' => 'zapamti me',
    ],

];
