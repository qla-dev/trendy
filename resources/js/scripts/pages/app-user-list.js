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

  function initUserListTooltips(scope) {
    var root = scope || document;

    if (window.bootstrap && window.bootstrap.Tooltip) {
      root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        var instance = window.bootstrap.Tooltip.getInstance(element);

        if (instance) {
          instance.dispose();
        }

        new window.bootstrap.Tooltip(element);
      });
    }
  }

  select.each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>');
    $this.select2({
      dropdownAutoWidth: true,
      width: '100%',
      dropdownParent: $this.parent()
    });
  });

  if (dtUserTable.length) {
    dtUserTable.DataTable({
      data: window.usersData || [],
      columns: [{ data: null }, { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 }, { data: 5 }],
      columnDefs: [
        {
          targets: 0,
          responsivePriority: 1,
          render: function (data, type, full, meta) {
            var $name = full[0] || '';
            var $initials = $name.match(/\b\w/g) || [];
            $initials = (($initials.shift() || '') + ($initials.pop() || '')).toUpperCase();
            var $output = '<span class="avatar-content">' + $initials + '</span>';
            var colorClass = ' bg-light-primary ';

            return (
              '<div class="avatar-wrapper">' +
              '<div class="avatar ' +
              colorClass +
              '">' +
              $output +
              '</div>' +
              '</div>'
            );
          }
        },
        {
          targets: 1,
          render: function (data, type, full, meta) {
            return (
              '<a href="' +
              userView +
              '/' +
              full[5] +
              '" class="user_name text-truncate text-body"><span class="fw-bolder">' +
              (full[0] || '-') +
              '</span></a>'
            );
          }
        },
        {
          targets: 2,
          render: function (data, type, full, meta) {
            return "<span class='text-truncate align-middle'>" + full[1] + '</span>';
          }
        },
        {
          targets: 3,
          render: function (data, type, full, meta) {
            return "<span class='text-truncate align-middle'>" + (full[2] || '-') + '</span>';
          }
        },
        {
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
          targets: 5,
          render: function (data, type, full, meta) {
            return '<span class="text-nowrap">' + full[4] + '</span>';
          }
        },
        {
          targets: -1,
          title: 'Akcije',
          orderable: false,
          className: 'text-end user-list-action-cell',
          responsivePriority: 2,
          render: function (data, type, full, meta) {
            return (
              '<div class="app-table-action-group">' +
              '<a href="' +
              userView +
              '/' +
              full[5] +
              '" class="btn btn-sm app-table-action-btn app-table-action-btn--info" data-bs-toggle="tooltip" data-bs-placement="top" title="Pregled korisnika" aria-label="Pregled korisnika">' +
              feather.icons['eye'].toSvg({ class: 'font-small-4' }) +
              '</a>' +
              '<button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Obriši korisnika" aria-label="Obriši korisnika" onclick="deleteUser(' +
              full[5] +
              ')">' +
              feather.icons['trash-2'].toSvg({ class: 'font-small-4' }) +
              '</button>' +
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
        sDecimal: '',
        sEmptyTable: 'Nema podataka u tabeli',
        sInfo: 'Prikazano _START_ do _END_ od _TOTAL_ korisnika',
        sInfoEmpty: 'Prikazano 0 do 0 od 0 korisnika',
        sInfoFiltered: '(filtrirano od _MAX_ ukupno korisnika)',
        sInfoPostFix: '',
        sThousands: ',',
        sLengthMenu: 'Prikaži _MENU_',
        sLoadingRecords: 'Učitavanje...',
        sProcessing: 'Obrađuje se...',
        sSearch: 'Pretraži:',
        sSearchPlaceholder: 'Pojam za pretragu..',
        sZeroRecords: 'Nisu pronađeni odgovarajući zapisi',
        oPaginate: {
          sFirst: 'Prva',
          sLast: 'Poslednja',
          sNext: 'Sljedeća',
          sPrevious: 'Prethodna'
        },
        oAria: {
          sSortAscending: ': aktiviraj za rastuće sortiranje kolone',
          sSortDescending: ': aktiviraj za opadajuće sortiranje kolone'
        }
      },
      responsive: false,
      language: {
        paginate: {
          previous: '&nbsp;',
          next: '&nbsp;'
        }
      },
      drawCallback: function () {
        if (window.feather && typeof window.feather.replace === 'function') {
          window.feather.replace();
        }

        initUserListTooltips(document);
      },
      initComplete: function () {
        if (window.feather && typeof window.feather.replace === 'function') {
          window.feather.replace();
        }

        initUserListTooltips(document);

        this.api()
          .columns(4)
          .every(function () {
            var column = this;
            $('<label class="form-label" for="UserRole">Uloga</label>').appendTo('.user_role');
            var select = $('<select id="UserRole" class="form-select text-capitalize mb-md-0 mb-2"><option value=""> Odaberite Ulogu </option></select>')
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

  if (newUserForm.length) {
    function setPasswordFeedback(message, state) {
      if (!passwordMatchFeedback.length) {
        return;
      }

      passwordMatchFeedback.toggleClass('d-none', !message).removeClass('text-success text-danger').text(message || '');

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
        name: {
          required: true
        },
        username: {
          required: true
        },
        email: {
          email: true
        },
        password: {
          required: true,
          minlength: 6
        },
        password_confirmation: {
          required: true,
          equalTo: '#password'
        }
      },
      messages: {
        password_confirmation: {
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
        return true;
      }

      e.preventDefault();
    });
  }

  if (dtContact.length) {
    dtContact.each(function () {
      new Cleave($(this), {
        phone: true,
        phoneRegionCode: 'US'
      });
    });
  }
});
