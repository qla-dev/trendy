/*=========================================================================================
    File Name: app-user-list.js
    Description: User List page
    --------------------------------------------------------------------------------------
    Item Name: Vuexy  - Vuejs, HTML & Laravel Admin Dashboard Template
    Author: PIXINVENT
    Author URL: http://www.themeforest.net/user/pixinvent

==========================================================================================*/
$(function () {
  ('use strict');

  var dtUserTable = $('.user-list-table'),
    newUserSidebar = $('#addUserModal'),
    newUserForm = $('#addUserForm'),
    passwordField = $('#password'),
    passwordConfirmationField = $('#password_confirmation'),
    passwordMatchFeedback = $('#password-match-feedback'),
    select = $('.select2'),
    dtContact = $('.dt-contact'),
    statusObj = {
      1: { title: 'Pending', class: 'badge-light-warning' },
      2: { title: 'Active', class: 'badge-light-success' },
      3: { title: 'Inactive', class: 'badge-light-secondary' }
    };

  var assetPath = '../../../app-assets/',
    userView = 'app-user-view-account.html';

  if ($('body').attr('data-framework') === 'laravel') {
    assetPath = $('body').attr('data-asset-path');
    userView = '/app/user/view/account';
  }

  select.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>');
    $this.select2({
      // the following code is used to disable x-scrollbar when click in select input and
      // take 100% width in responsive also
      dropdownAutoWidth: true,
      width: '100%',
      dropdownParent: $this.parent()
    });
  });

  // Users List datatable
  if (dtUserTable.length) {
    console.log('Initializing DataTable with Bosnian language...');
    dtUserTable.DataTable({
      data: window.usersData || [],
      columns: [
        { data: null },
        { data: 0 },
        { data: 1 },
        { data: 2 },
        { data: 3 },
        { data: 4 },
        { data: 5 }
      ],
      columnDefs: [
        {
          // Avatar
          targets: 0,
          responsivePriority: 1,
          render: function (data, type, full, meta) {
            var $name = full[0] || '';
            // For Avatar badge
            var $initials = $name.match(/\b\w/g) || [];
            $initials = (($initials.shift() || '') + ($initials.pop() || '')).toUpperCase();
            var $output = '<span class="avatar-content">' + $initials + '</span>';
            var colorClass = ' bg-light-primary ';
            var $row_output =
              '<div class="avatar-wrapper">' +
              '<div class="avatar ' +
              colorClass +
              '">' +
              $output +
              '</div>' +
              '</div>';
            return $row_output;
          }
        },
        {
          // User full name
          targets: 1,
          render: function (data, type, full, meta) {
            return (
              '<a href="' +
              userView +
              '" class="user_name text-truncate text-body"><span class="fw-bolder">' +
              (full[0] || '-') +
              '</span></a>'
            );
          }
        },
        {
          // Username
          targets: 2,
          render: function (data, type, full, meta) {
            return "<span class='text-truncate align-middle'>" + full[1] + '</span>';
          }
        },
        {
          // Email
          targets: 3,
          render: function (data, type, full, meta) {
            return "<span class='text-truncate align-middle'>" + (full[2] || '-') + '</span>';
          }
        },
        {
          // User Role
          targets: 4,
          render: function (data, type, full, meta) {
            var $role = full[3];
            var roleBadgeObj = {
              admin: feather.icons['slack'].toSvg({ class: 'font-medium-3 text-danger me-50' }),
              user: feather.icons['user'].toSvg({ class: 'font-medium-3 text-primary me-50' })
            };
            var roleText = $role === 'admin' ? 'Admin' : 'Korisnik';
            return "<span class='text-truncate align-middle'>" + roleBadgeObj[$role] + roleText + '</span>';
          }
        },
        {
          // Created Date
          targets: 5,
          render: function (data, type, full, meta) {
            var $date = full[4];
            return '<span class="text-nowrap">' + $date + '</span>';
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Akcije',
          orderable: false,
          responsivePriority: 2,
          render: function (data, type, full, meta) {
            return (
              '<div class="btn-group">' +
              '<a class="btn btn-sm dropdown-toggle hide-arrow" data-bs-toggle="dropdown">' +
              feather.icons['more-vertical'].toSvg({ class: 'font-small-4' }) +
              '</a>' +
              '<div class="dropdown-menu dropdown-menu-end">' +
              // '<a href="' + userView + '/' + full[6] + '" class="dropdown-item">' +
              // feather.icons['file-text'].toSvg({ class: 'font-small-4 me-50' }) +
              // 'Pregled</a>' +
              '<a href="javascript:;" class="dropdown-item">' +
              feather.icons['lock'].toSvg({ class: 'font-small-4 me-50' }) +
              'Promijeni lozinku</a>' +
              '<a href="javascript:;" class="dropdown-item delete-record" onclick="deleteUser(' + full[5] + ')">' +
              feather.icons['trash-2'].toSvg({ class: 'font-small-4 me-50' }) +
              'Obriši</a></div>' +
              '</div>' +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"d-flex justify-content-between align-items-center header-actions mx-2 row mt-75"' +
        '<"col-sm-12 col-lg-4 d-flex justify-content-center justify-content-lg-start" l>' +
        '<"col-sm-12 col-lg-8 ps-xl-75 ps-0"<"dt-action-buttons d-flex align-items-center justify-content-center justify-content-lg-end flex-lg-nowrap flex-wrap"<"me-1"f>>>' +
        '>t' +
        '<"d-flex justify-content-between mx-2 row"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      oLanguage: {
        "sDecimal": "",
        "sEmptyTable": "Nema podataka u tabeli",
        "sInfo": "Prikazano _START_ do _END_ od _TOTAL_ korisnika",
        "sInfoEmpty": "Prikazano 0 do 0 od 0 korisnika",
        "sInfoFiltered": "(filtrirano od _MAX_ ukupno korisnika)",
        "sInfoPostFix": "",
        "sThousands": ",",
        "sLengthMenu": "Prikaži _MENU_",
        "sLoadingRecords": "Učitavanje...",
        "sProcessing": "Obrađuje se...",
        "sSearch": "Pretraži:",
        "sSearchPlaceholder": "Pojam za pretragu..",
        "sZeroRecords": "Nisu pronađeni odgovarajući zapisi",
        "oPaginate": {
          "sFirst": "Prva",
          "sLast": "Poslednja",
          "sNext": "Sljedeća",
          "sPrevious": "Prethodna"
        },
        "oAria": {
          "sSortAscending": ": aktiviraj za rastuće sortiranje kolone",
          "sSortDescending": ": aktiviraj za opadajuće sortiranje kolone"
        }
      },
      responsive: false,
      language: {
        paginate: {
          // remove previous & next text from pagination
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      initComplete: function () {
        // Adding role filter once table initialized
        this.api()
          .columns(4)
          .every(function () {
            var column = this;
            var label = $('<label class="form-label" for="UserRole">Uloga</label>').appendTo('.user_role');
            var select = $(
              '<select id="UserRole" class="form-select text-capitalize mb-md-0 mb-2"><option value=""> Odaberite Ulogu </option></select>'
            )
              .appendTo('.user_role')
              .on('change', function () {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                column.search(val ? '^' + val + '$' : '', true, false).draw();
              });

            column
              .data()
              .unique()
              .sort()
              .each(function (d, j) {
                select.append('<option value="' + d + '" class="text-capitalize">' + d + '</option>');
              });
          });
      }
    });
  }

  // Form Validation
  if (newUserForm.length) {
    function setPasswordFeedback(message, state) {
      if (!passwordMatchFeedback.length) {
        return;
      }

      passwordMatchFeedback
        .toggleClass('d-none', !message)
        .removeClass('text-success text-danger')
        .text(message || '');

      if (message) {
        passwordMatchFeedback.addClass(state === 'success' ? 'text-success' : 'text-danger');
      }
    }

    function updatePasswordMatchState() {
      if (!passwordField.length || !passwordConfirmationField.length || !passwordMatchFeedback.length) {
        return;
      }

      var passwordValue = String(passwordField.val() || '');
      var confirmationValue = String(passwordConfirmationField.val() || '');

      passwordConfirmationField.removeClass('is-valid is-invalid');
      setPasswordFeedback('', null);

      if (!confirmationValue.length) {
        return;
      }

      if (passwordValue === confirmationValue) {
        passwordConfirmationField.addClass('is-valid');
        setPasswordFeedback('Šifre se poklapaju.', 'success');
        return;
      }

      passwordConfirmationField.addClass('is-invalid');
      setPasswordFeedback('Šifre se ne poklapaju.', 'danger');
    }

    newUserForm.validate({
      errorClass: 'error',
      errorPlacement: function (error, element) {
        if (element.attr('name') === 'password_confirmation') {
          passwordConfirmationField.removeClass('is-valid').addClass('is-invalid');
          setPasswordFeedback(error.text(), 'danger');
          return;
        }

        error.insertAfter(element);
      },
      rules: {
        'name': {
          required: true
        },
        'username': {
          required: true
        },
        'email': {
          email: true
        },
        'password': {
          required: true,
          minlength: 6
        },
        'password_confirmation': {
          required: true,
          equalTo: '#password'
        }
      },
      messages: {
        'password_confirmation': {
          required: 'Potvrda lozinke je obavezna.',
          equalTo: 'Šifre se ne poklapaju.'
        }
      }
    });

    passwordField.on('input', function () {
      updatePasswordMatchState();
      if (passwordConfirmationField.val()) {
        passwordConfirmationField.valid();
      }
    });

    passwordConfirmationField.on('input', function () {
      updatePasswordMatchState();
      passwordConfirmationField.valid();
    });

    newUserSidebar.on('hidden.bs.modal', function () {
      if (passwordConfirmationField.length) {
        passwordConfirmationField.removeClass('is-valid is-invalid');
      }

      if (passwordMatchFeedback.length) {
        setPasswordFeedback('', null);
      }
    });

    updatePasswordMatchState();

    newUserForm.on('submit', function (e) {
      updatePasswordMatchState();
      var isValid = newUserForm.valid();
      if (isValid) {
        // Form will submit normally to the server
        return true;
      }
      e.preventDefault();
    });
  }

  // Phone Number
  if (dtContact.length) {
    dtContact.each(function () {
      new Cleave($(this), {
        phone: true,
        phoneRegionCode: 'US'
      });
    });
  }
});
