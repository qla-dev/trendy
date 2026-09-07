@props(['label', 'placeholder' => 'dd.mm.yyyy'])

<div class="shared-filter-field">
  <label class="form-label">{{ $label }}</label>
  <div class="input-group input-group-merge">
    <span class="input-group-text"><i data-feather="calendar"></i></span>
    <input type="text" placeholder="{{ $placeholder }}" autocomplete="off" {{ $attributes->class(['form-control', 'shared-filter-control', 'shared-filter-date']) }}>
  </div>
</div>
