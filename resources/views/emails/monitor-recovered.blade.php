@component('mail::message')
# ✅ Monitor Recovered

@if(!empty($teamName))
**Team:** {{ $teamName }}
@endif

**{{ $monitorName }}** is back **UP**.

- **URL:** {{ $monitorUrl }}
- **Down since:** {{ $incidentStartedAt }}
- **Recovered at:** {{ $incidentRecoveredAt }}
- **Downtime:** {{ $downtimeHuman }} ({{ $downtimeSeconds }}s)

@component('mail::button', ['url' => $appMonitorUrl])
View Monitor
@endcomponent

Thanks,  
**Monitly**
@endcomponent
