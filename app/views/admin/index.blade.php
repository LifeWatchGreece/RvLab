<div>

    <div style="position: relative; padding: 0px 3px; top:9px; left:20px; display: inline; background-color: #f7f7f7; color: #8C8C8C; z-index: 100">Admin Pages:</div>
    <div style="border: 1px solid #D2D2D2; padding: 25px">
        <a class="speed_button" href="{{ url('admin/job_list') }}">
            <img src="{{ asset('images/job_list.png') }}" />
            <div class="speed_text">Recent Jobs List</div>
        </a>
        <a class="speed_button" href="{{ url('admin/last_errors') }}">
            <img src="{{ asset('images/error_list.png') }}" />
            <div class="speed_text">Recent Errors</div>
        </a>
        <a class="speed_button" href="{{ url('admin/storage_utilization') }}">
            <img src="{{ asset('images/storage_util.png') }}" />
            <div class="speed_text">Storage Utilization</div>
        </a>
        <a class="speed_button" href="{{ url('admin/statistics') }}">
            <img src="{{ asset('images/statistics.png') }}" />
            <div class="speed_text">Usage Statistics</div>
        </a>
        <a class="speed_button" href="{{ url('admin/configure') }}">
            <img src="{{ asset('images/configure.png') }}" />
            <div class="speed_text">System Configuration</div>
        </a>
    </div>

</div>
