@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #0b4a3e; font-size: 20px; font-weight: 600; text-decoration: none;">
<span style="display: inline-block; width: 12px; height: 12px; border-radius: 9999px; background: #5ce8a6; margin: 0 6px;"></span>{{ $slot }}
</a>
</td>
</tr>
