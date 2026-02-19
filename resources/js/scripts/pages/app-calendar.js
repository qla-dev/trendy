/**
 * App Calendar - Work Orders
 */

'use strict';

// RTL Support
var direction = 'ltr',
  assetPath = '../../../app-assets/';

if ($('html').data('textdirection') === 'rtl') {
  direction = 'rtl';
}

if ($('body').attr('data-framework') === 'laravel') {
  assetPath = $('body').attr('data-asset-path');
}

$(document).on('click', '.fc-sidebarToggle-button', function () {
  $('.app-calendar-sidebar, .body-content-overlay').addClass('show');
});

$(document).on('click', '.body-content-overlay', function () {
  $('.app-calendar-sidebar, .body-content-overlay').removeClass('show');
});

document.addEventListener('DOMContentLoaded', function () {
  var appCalendarRoot = $('.app-calendar');
  var calendarEl = document.getElementById('calendar');

  if (!appCalendarRoot.length || !calendarEl) {
    return;
  }

  var sidebar = $('.event-sidebar'),
    eventForm = $('.event-form'),
    addEventBtn = $('.add-event-btn'),
    cancelBtn = $('.btn-cancel'),
    updateEventBtn = $('.update-event-btn'),
    toggleSidebarBtn = $('.btn-toggle-sidebar'),
    eventTitle = $('#title'),
    eventLabel = $('#select-label'),
    startDate = $('#start-date'),
    endDate = $('#end-date'),
    eventUrl = $('#event-url'),
    eventGuests = $('#event-guests'),
    eventLocation = $('#event-location'),
    allDaySwitch = $('.allDay-switch'),
    selectAll = $('.select-all'),
    calEventFilter = $('.calendar-events-filter'),
    filterInput = $('.input-filter'),
    btnDeleteEvent = $('.btn-delete-event'),
    calendarEditor = $('#event-description-editor'),
    addMeetingBtn = $('.btn-add-meeting');

  var workOrdersCalendarApi =
    appCalendarRoot.data('work-orders-calendar-url') || assetPath + 'api/work-orders-calendar';
  var previewBaseUrl =
    appCalendarRoot.data('work-order-preview-base-url') || assetPath + 'app/invoice/preview';

  var calendarsColor = {
    planiran: 'info',
    otvoren: 'success',
    rezerviran: 'warning',
    u_radu: 'primary',
    djelimicno_zakljucen: 'warning',
    zakljucen: 'danger',
    raspisan: 'secondary'
  };

  var monthNames = [
    'Januar',
    'Februar',
    'Mart',
    'April',
    'Maj',
    'Juni',
    'Juli',
    'Avgust',
    'Septembar',
    'Oktobar',
    'Novembar',
    'Decembar'
  ];
  var monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Avg', 'Sep', 'Okt', 'Nov', 'Dec'];
  var dayNamesShort = ['Ned', 'Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub'];
  var dayNamesLong = ['Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota'];

  var startPicker = null;
  var endPicker = null;

  function capitalize(text) {
    if (!text || typeof text !== 'string') {
      return text;
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function normalizeDateOnly(dateInput) {
    if (!(dateInput instanceof Date)) {
      return '';
    }

    return dateInput.toISOString().slice(0, 10);
  }

  function selectedStatuses() {
    var selected = [];

    $('.calendar-events-filter input:checked').each(function () {
      selected.push($(this).attr('data-value'));
    });

    return selected;
  }

  function updateStatusCounts(statusStats) {
    var resolvedStats = statusStats || {};

    $('.calendar-filter-count').each(function () {
      var statKey = $(this).data('stat-key');
      var statValue = Object.prototype.hasOwnProperty.call(resolvedStats, statKey) ? resolvedStats[statKey] : 0;
      $(this).text(statValue);
    });
  }

  function modifyToggler() {
    if (typeof feather === 'undefined') {
      return;
    }

    $('.fc-sidebarToggle-button')
      .empty()
      .append(feather.icons['menu'].toSvg({ class: 'ficon' }));
  }

  function localizeCalendarTitle(view) {
    var titleEl = calendarEl.querySelector('.fc-toolbar-title');

    if (!titleEl || !view) {
      return;
    }

    var rangeStart = view.currentStart;
    var rangeEnd = new Date(view.currentEnd.getTime() - 86400000);
    var titleText = titleEl.textContent || '';
    var startDay = rangeStart.getDate();
    var endDay = rangeEnd.getDate();
    var startMonth = rangeStart.getMonth();
    var endMonth = rangeEnd.getMonth();
    var startYear = rangeStart.getFullYear();
    var endYear = rangeEnd.getFullYear();

    if (view.type === 'dayGridMonth' || view.type === 'listMonth') {
      titleText = monthNames[startMonth] + ' ' + startYear;
    } else if (view.type === 'timeGridWeek') {
      var sameMonthYear = startMonth === endMonth && startYear === endYear;

      if (sameMonthYear) {
        titleText = startDay + '. - ' + endDay + '. ' + monthNames[endMonth] + ' ' + endYear;
      } else if (startYear === endYear) {
        titleText =
          startDay +
          '. ' +
          monthNamesShort[startMonth] +
          ' - ' +
          endDay +
          '. ' +
          monthNamesShort[endMonth] +
          ' ' +
          endYear;
      } else {
        titleText =
          startDay +
          '. ' +
          monthNamesShort[startMonth] +
          ' ' +
          startYear +
          ' - ' +
          endDay +
          '. ' +
          monthNamesShort[endMonth] +
          ' ' +
          endYear;
      }
    } else if (view.type === 'timeGridDay') {
      titleText =
        dayNamesLong[rangeStart.getDay()] + ', ' + startDay + '. ' + monthNames[startMonth] + ' ' + startYear;
    }

    titleEl.textContent = titleText;
  }

  function resolvePreviewUrl(calendarEvent) {
    var previewUrl =
      calendarEvent.extendedProps && calendarEvent.extendedProps.previewUrl
        ? calendarEvent.extendedProps.previewUrl
        : '';

    if (previewUrl) {
      return previewUrl;
    }

    var workOrderId =
      (calendarEvent.extendedProps && calendarEvent.extendedProps.workOrderId) ||
      (calendarEvent.id && calendarEvent.id.toString ? calendarEvent.id.toString() : '');

    if (!workOrderId) {
      return '';
    }

    return previewBaseUrl.replace(/\/$/, '') + '/' + workOrderId;
  }

  function resetValues() {
    endDate.val('');
    eventUrl.val('');
    startDate.val('');
    eventTitle.val('');
    eventLocation.val('');
    allDaySwitch.prop('checked', false);
    eventGuests.val('').trigger('change');
    calendarEditor.val('');
  }

  function fetchEvents(fetchInfo, successCallback, failureCallback) {
    var activeStatuses = selectedStatuses();
    var applyStatusFilter = !selectAll.prop('checked');
    var requestData = {
      start: normalizeDateOnly(fetchInfo.start),
      end: normalizeDateOnly(fetchInfo.end)
    };

    if (applyStatusFilter) {
      requestData.statuses = activeStatuses;
    }

    $.ajax({
      url: workOrdersCalendarApi,
      method: 'GET',
      dataType: 'json',
      data: requestData,
      success: function (response) {
        updateStatusCounts(response.statusStats || {});

        var calendarEvents = Array.isArray(response.data) ? response.data : [];

        if (applyStatusFilter && activeStatuses.length === 0) {
          calendarEvents = [];
        }

        successCallback(calendarEvents);
      },
      error: function (xhr) {
        updateStatusCounts({});
        successCallback([]);

        if (typeof failureCallback === 'function') {
          failureCallback(xhr);
        }
      }
    });
  }

  if (addMeetingBtn.length) {
    addMeetingBtn.on('click', function () {
      window.alert('Uskoro');
    });
  }

  if (eventLabel.length) {
    function renderBullets(option) {
      if (!option.id) {
        return option.text;
      }

      var bullet =
        "<span class='bullet bullet-" +
        $(option.element).data('label') +
        " bullet-sm me-50'> " +
        '</span>' +
        option.text;

      return bullet;
    }

    eventLabel.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: eventLabel.parent(),
      templateResult: renderBullets,
      templateSelection: renderBullets,
      minimumResultsForSearch: -1,
      escapeMarkup: function (es) {
        return es;
      }
    });
  }

  if (eventGuests.length) {
    function renderGuestAvatar(option) {
      if (!option.id) {
        return option.text;
      }

      var avatar =
        "<div class='d-flex flex-wrap align-items-center'>" +
        "<div class='avatar avatar-sm my-0 me-50'>" +
        "<span class='avatar-content'>" +
        "<img src='" +
        assetPath +
        'images/avatars/' +
        $(option.element).data('avatar') +
        "' alt='avatar' />" +
        '</span>' +
        '</div>' +
        option.text +
        '</div>';

      return avatar;
    }

    eventGuests.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: eventGuests.parent(),
      closeOnSelect: false,
      templateResult: renderGuestAvatar,
      templateSelection: renderGuestAvatar,
      escapeMarkup: function (es) {
        return es;
      }
    });
  }

  if (startDate.length) {
    startPicker = startDate.flatpickr({
      enableTime: true,
      altFormat: 'Y-m-dTH:i:S',
      onReady: function (selectedDates, dateStr, instance) {
        if (instance.isMobile) {
          $(instance.mobileInput).attr('step', null);
        }
      }
    });
  }

  if (endDate.length) {
    endPicker = endDate.flatpickr({
      enableTime: true,
      altFormat: 'Y-m-dTH:i:S',
      onReady: function (selectedDates, dateStr, instance) {
        if (instance.isMobile) {
          $(instance.mobileInput).attr('step', null);
        }
      }
    });
  }

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    events: fetchEvents,
    editable: false,
    dragScroll: true,
    dayMaxEvents: 2,
    eventResizableFromStart: false,
    customButtons: {
      sidebarToggle: {
        text: 'Sidebar'
      }
    },
    headerToolbar: {
      start: 'sidebarToggle, prev,next, title',
      end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
    },
    direction: direction,
    initialDate: new Date(),
    navLinks: true,
    firstDay: 1,
    locale: 'en-gb',
    buttonText: {
      today: 'Danas',
      month: 'Mjesec',
      week: 'Sedmica',
      day: 'Dan',
      list: 'Lista'
    },
    allDayText: 'Cijeli dan',
    noEventsContent: 'Nema zapisa za prikaz',
    moreLinkText: function (amount) {
      return '+ jos ' + amount;
    },
    dayHeaderContent: function (args) {
      return dayNamesShort[args.date.getDay()];
    },
    eventClassNames: function (info) {
      var colorName = calendarsColor[info.event.extendedProps.calendar] || 'secondary';

      return ['bg-light-' + colorName];
    },
    dateClick: function (info) {
      var date = normalizeDateOnly(info.date);

      resetValues();
      sidebar.modal('show');
      addEventBtn.removeClass('d-none');
      updateEventBtn.addClass('d-none');
      btnDeleteEvent.addClass('d-none');

      if (startPicker) {
        startPicker.setDate(date, true, 'Y-m-d');
      } else {
        startDate.val(date);
      }

      if (endPicker) {
        endPicker.setDate(date, true, 'Y-m-d');
      } else {
        endDate.val(date);
      }
    },
    eventClick: function (info) {
      info.jsEvent.preventDefault();

      var previewUrl = resolvePreviewUrl(info.event);

      if (previewUrl) {
        window.location.href = previewUrl;
      }
    },
    datesSet: function (info) {
      modifyToggler();
      localizeCalendarTitle(info.view);
    },
    viewDidMount: function (info) {
      modifyToggler();
      localizeCalendarTitle(info.view);
    }
  });

  calendar.render();
  modifyToggler();

  if (eventForm.length) {
    eventForm.validate({
      submitHandler: function (form, event) {
        event.preventDefault();
        if (eventForm.valid()) {
          sidebar.modal('hide');
        }
      },
      title: {
        required: true
      },
      rules: {
        'start-date': { required: true },
        'end-date': { required: true }
      },
      messages: {
        'start-date': { required: 'Start Date is required' },
        'end-date': { required: 'End Date is required' }
      }
    });
  }

  sidebar.on('hidden.bs.modal', function () {
    resetValues();
  });

  if (toggleSidebarBtn.length) {
    toggleSidebarBtn.on('click', function () {
      cancelBtn.removeClass('d-none');
      btnDeleteEvent.addClass('d-none');
      updateEventBtn.addClass('d-none');
      addEventBtn.removeClass('d-none');
      $('.app-calendar-sidebar, .body-content-overlay').removeClass('show');
    });
  }

  if (selectAll.length) {
    selectAll.on('change', function () {
      var self = $(this);
      calEventFilter.find('input').prop('checked', self.prop('checked'));
      calendar.refetchEvents();
    });
  }

  if (filterInput.length) {
    filterInput.on('change', function () {
      $('.input-filter:checked').length < calEventFilter.find('input').length
        ? selectAll.prop('checked', false)
        : selectAll.prop('checked', true);

      calendar.refetchEvents();
    });
  }
});
