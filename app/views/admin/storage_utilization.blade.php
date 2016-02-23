<?php    
    $user_soft_limit = $rvlab_storage_limit / $max_users_suported; // in KB    
    
    if($utilized_space > 1000000)
        $utilized_text = number_format($utilized_space/1000000,2)." GB";
    elseif($utilized_space > 1000)
        $utilized_text = number_format($utilized_space/1000,2)." MB";
    else
        $utilized_text = number_format($utilized_space,2)." KB";
?>

@include("admin.bar")

<div class="col-sm-12">
    <div style='font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 30px'>Total Storage Utilization</div>

    <style type='text/css'>
        #workspace_util_table td {
            border-top: 0px;
        }
    </style>

    <table class='table thin-table' id='workspace_util_table'>
        <tbody>
            <tr>
                <td style='text-align: left'><label></label></td>
                <td style='width: 90%'>                
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" aria-valuenow="{{ $utilization }}" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: {{ $utilization }}%">
                          {{ $utilization }}%
                        </div>
                    </div>
                </td>
                <td style='min-width: 150px; text-align: left'>{{ $utilized_text }}</td>
            </tr>      
        </tbody>
    </table>

    <div style='font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 30px'>Per User Storage Utilization</div>

    <table class='table thin-table' id='workspace_util_table'>
        <tbody>
            @foreach($user_totals as $user_email => $totalsize)
                <? 
                    $progress = number_format(100*$totalsize/$user_soft_limit,1); 
                    if($totalsize > 1000000){
                        $size_text = number_format($totalsize/1000000,2)." GB";
                    } elseif($totalsize > 1000) {
                        $size_text = number_format($totalsize/1000,2)." MB";
                    } else {
                        $size_text = number_format($totalsize,2)." KB";
                    }
                ?>
                @if($progress <= 100)
                    <tr>
                        <td style='text-align: left'><label>{{ $user_email }}</label></td>
                        <td style='width: 90%'>                
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: {{ $progress }}%">
                                  {{ $progress }}%
                                </div>
                            </div>
                        </td>
                        <td style='min-width: 150px; text-align: left'>{{ $size_text }}</td>
                    </tr>
                @else
                    <tr>
                        <td style='text-align: left'><label>{{ $user_email }}</label></td>
                        <td style='width: 90%'>                
                            <div class="progress">
                                <div class="red-progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: 100%">
                                  {{ $progress }}%
                                </div>
                            </div>
                        </td>
                        <td style='min-width: 150px; text-align: left'>{{ $size_text }}</td>
                    </tr>
                @endif        
            @endforeach
        </tbody>
    </table>
</div>


