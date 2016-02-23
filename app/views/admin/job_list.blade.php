
@include("admin.bar")

<div class="col-sm-12">
        <label style='color: blue'>Last 50 jobs:</label>

        <table class='table table-bordered table-condensed'>
            <thead>
                <th>Job ID</th>
                <th>Function</th>
                <th>User</th>
                <th>Status</th>
                <th>Submitted At</th>
            </thead>
            <tbody>
                @foreach($job_list as $job)
                <tr>
                    <td>Job{{ $job->id }}</td>
                    <td>{{ $job->function }}</td>
                    <td>{{ $job->user_email }}</td>
                    <td style='text-align: center'>
                        <?
                            switch($job->status){
                                case 'creating':
                                    echo "<div class='job_status status_creating'>Creating...</div>";
                                    break;
                                case 'submitted':
                                    echo "<div class='job_status status_submitted'>Submitted</div>";
                                    break;
                                case 'queued':
                                    echo "<div class='job_status status_queued'>Queued</div>";
                                    break;
                                case 'completed':
                                    echo "<div class='job_status status_completed'>Completed</div>";
                                    break;
                                case 'failed':
                                    echo "<div class='job_status status_failed'>Failed</div>";
                                    break;
                            }
                        ?>
                    </td>
                    <td>{{ $job->submitted_at }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
</div>



