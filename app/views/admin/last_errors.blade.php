@include("admin.bar")

<div class="col-sm-12">
    <label style='color: blue'>Last 20 errors:</label>

    <table class='table table-bordered table-condensed'>
        <thead>
            <th>When</th>
            <th>User</th>
            <th>Controller</th>
            <th>Method</th>
            <th>Message</th>
        </thead>
        <tbody>
            @foreach($error_list as $item)
            <tr>
                <td style='min-width: 160px'>{{ $item->when }}</td>
                <td>{{ $item->user_email }}</td>
                <td>{{ $item->controller }}</td>
                <td>{{ $item->method }}</td>
                <td style='text-align: left'>{{ $item->message }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>



