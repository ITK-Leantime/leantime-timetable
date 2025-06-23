@extends($layout)

@section('content')

    <x-global::pageheader :icon="'fa fa-puzzle-piece'">
        <h1>TimeTable Settings</h1>
    </x-global::pageheader>

    <div class="maincontent">
        <?php if (isset($tpl)) {
            echo $tpl->displayNotification();
        } ?>
        <div class="maincontentinner timetable-settings">
            <h5 class="subtitle">TimeTable Settings</h5>
            <p class="tw-pb-m">These settings will change the way the TimeTable plugin works.</p>

            <form method="post" id="" action="<?= BASE_URL ?>/TimeTable/settings">
                <div class="row">
                    <div class="col-md-2">
                        <label>Require timelog comment</label>
                    </div>
                    <div class="col-md-8">
                        <input type="checkbox" value="1" name="requireTimeRegistrationComment"
                            {{ (int) $requireTimeRegistrationComment === 1 ? 'checked' : '' }} />
                    </div>
                </div>
                <br>
                <input type="submit" value="Save" id="saveBtn" />
            </form>

        </div>
    </div>
@endsection
