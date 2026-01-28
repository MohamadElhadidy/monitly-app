@component('mail::message')
# 🔴 Monitor Down

@if(!empty($teamName))
**Team:** {{ $teamName }}
@endif

**{{ $monitorName }}** is currently **DOWN**.

- **URL:** {{ $monitorUrl }}
- **Detected at:** {{ $incidentStartedAt }}

@component('mail::button', ['url' => $appMonitorUrl])
View Monitor
@endcomponent

If this looks unexpected, verify DNS/SSL and confirm the endpoint is reachable publicly.

Thanks,  
**Monitly**
@endcomponent
