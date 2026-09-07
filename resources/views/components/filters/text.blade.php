@props(['label', 'placeholder' => '', 'icon' => 'search'])

<div class="shared-filter-field">
  <label class="form-label">{{ $label }}</label>
  <div class="input-group input-group-merge">
    <span class="input-group-text"><i data-feather="{{ $icon }}"></i></span>
    <input type="text" placeholder="{{ $placeholder ?: $label }}" {{ $attributes->class(['form-control', 'shared-filter-control']) }}>
  </div>
</div>
