@var('script_name', $attributes->get(':script'))
@var('script_dependencies', strlen($depends = (string) $attributes->get(':depends')) ? array_map('trim', explode(',', $depends)) : [])
@var('script_content', $slot)
@php
app()->make($factoryClass)->useScript($script_name, $script_dependencies, (string) $script_content);
@endphp