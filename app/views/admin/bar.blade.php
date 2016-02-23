<div class="col-sm-12" style="margin-bottom: 20px">
    <div class="panel panel-default" style="margin-bottom: 0px">
        <div class="panel-heading" style="background-color: #FAB6AE">
            <span class="glyphicon glyphicon-file" aria-hidden="true"></span> 
            <strong>Admin Toolbar</strong>
            <div style="display: inline; text-align: left">
                <a href="{{ url('/') }}" title="Home" class="admin_speed_links"><img src="{{ asset('images/home.png') }}" style="width:20px" /></a>
                <a href="{{ url('admin/job_list') }}" title="Recent Jobs" class="admin_speed_links"><img src="{{ asset('images/job_list.png') }}" style="width:20px" /></a>
                <a href="{{ url('admin/last_errors') }}" title="Recent Errors" class="admin_speed_links"><img src="{{ asset('images/error_list.png') }}" style="width:20px" /></a>
                <a href="{{ url('admin/storage_utilization') }}" title="Storage Utilization" class="admin_speed_links"><img src="{{ asset('images/storage_util.png') }}" style="width:20px" /></a>
                <a href="{{ url('admin/statistics') }}" title="Usage Statistics" class="admin_speed_links"><img src="{{ asset('images/statistics.png') }}" style="width:20px" /></a>
                <a href="{{ url('admin/configure') }}" title="System Configuration" class="admin_speed_links"><img src="{{ asset('images/configure.png') }}" style="width:20px" /></a>
            </div>
        </div>
    </div>
</div>
