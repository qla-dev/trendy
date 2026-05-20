{{-- For submenu --}}
<ul class="menu-content">
  @if (isset($menu))
    @foreach ($menu as $submenu)
      <li @if ($submenu->slug === Route::currentRouteName()) class="active" @endif>
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}" class="d-flex align-items-center"
          target="{{ isset($submenu->newTab) && $submenu->newTab === true ? '_blank' : '_self' }}">
          @if (isset($submenu->icon))
            @php
              $submenuIcon = (string) ($submenu->icon ?? '');
              $isFontAwesomeIcon = \Illuminate\Support\Str::startsWith($submenuIcon, ['fa ', 'fa-', 'fas ', 'far ', 'fal ', 'fab ']);
              $submenuIconClass = \Illuminate\Support\Str::startsWith($submenuIcon, 'fa-') ? 'fas ' . $submenuIcon : $submenuIcon;
            @endphp
            @if ($isFontAwesomeIcon)
              <i class="{{ trim($submenuIconClass) }} fa-fw" aria-hidden="true"></i>
            @else
              <i data-feather="{{ $submenu->icon }}"></i>
            @endif
          @endif
          <span class="menu-item text-truncate">{{ $submenu->name }}</span>
        </a>
        @if (isset($submenu->submenu))
          @include('panels/submenu', ['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
