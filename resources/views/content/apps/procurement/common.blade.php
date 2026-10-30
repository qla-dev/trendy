@once
<style>
/* Orders use 38px shared action buttons plus the standard .72rem cell padding. */
.procurement-compact tbody tr{height:calc(38px + 1.44rem + 1px)}
.procurement-compact{white-space:nowrap}.procurement-compact td,.procurement-compact th{vertical-align:middle;white-space:nowrap}.procurement-compact .material-description{max-width:280px;overflow:hidden;text-overflow:ellipsis}.procurement-compact td{max-width:300px;overflow:hidden;text-overflow:ellipsis}.procurement-input{min-width:110px}.procurement-compact th a{color:inherit}.procurement-loading{opacity:.55;pointer-events:none}
</style>
@endonce
@foreach (['success', 'warning'] as $message)
  @if(session($message))<div class="alert alert-{{ $message }} p-1" role="status">{{ session($message) }}</div>@endif
@endforeach
