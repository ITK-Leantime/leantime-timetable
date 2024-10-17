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
            <h4 class="widgettitle title-light"><span class="fa fa-cog"></span>
                Cache expiration durations
            </h4>
            <form method="post" id="" action="<?= BASE_URL ?>/TimeTable/settings">
                <div class="row">
                    <div class="col-md-2">
                        <label>Tickets (minutes)</label>
                    </div>
                    <div class="col-md-8">
                        <input type="number" min="0" value="{{ (int) $ticketCacheExpiration }}"
                            name="ticketCacheExpiration" />
                    </div>
                </div>
                <input type="submit" value="Save" id="saveBtn" />
            </form>

        </div>
    </div>
@endsection
