<div class="row">    

    @include("admin.bar")

    <div class="col-sm-12" style="margin-top: 20px">
        
        <label style='color: blue'>System Configuration:</label>
        <table class="table table-bordered">
            <thead>
                <th>Parameter</th>
                <th>Value</th>
                <th>Comments</th>
            </thead>
            <tbody>
                {{ Form::open(array('url'=>'admin/configure','class'=>'form-horizontal')) }}
                @foreach($settings as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td><input type="text" name="{{ $item->name }}" value="{{ $item->value }}" style="width:100%" class="form-control"></td>
                        <td style="width: 600px; text-align: left">{{ $item->about }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" style="text-align: right">
                        <button type="submit" class="btn btn-xs btn-primary">Save changes</button>
                    </td>
                </tr>
                {{ Form::close() }}
            </tbody>
        </table>
    </div>
    
</div>