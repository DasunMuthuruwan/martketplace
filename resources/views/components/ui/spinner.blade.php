@props(['size' => 20])
<x-ui.icon name="loader-circle" :size="$size" :class="trim('animate-spin text-fg-subtle motion-reduce:animate-none '.$attributes->get('class'))" />
