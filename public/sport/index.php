<?php
    session_start();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>The Second Directory</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="The Second Directory.">
        <link rel="stylesheet" type="text/css" href="/css/datatables.min.css"/>
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php
            $exercises = json_decode(file_get_contents('../../json/workout.json'), true)['exercises'];
            $plan = json_decode(file_get_contents('../../json/workout.json'), true)['plan'];
            $workouts = json_decode(file_get_contents('../../json/workout.json'), true)['workouts'];
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $nav = file_get_contents('../nav');

            function Authenticate(){
                global $users;
                foreach($users as $lola){
                    if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                        return true;
                    }
                }
                return false;
            }

            if(Authenticate()){
                usort($workouts, function($a, $b) {
                    $dateTimestamp1 = DateTime::createFromFormat('Y-m-d', $a["date"])->getTimestamp();
                    $dateTimestamp2 = DateTime::createFromFormat('Y-m-d', $b["date"])->getTimestamp();
                    
                    return $dateTimestamp1 < $dateTimestamp2 ? -1: 1;
                });

                $lastWorkoutDatePlusOne = date('Y-m-d'); 

                if (!empty($workouts)) {
                    $lastWorkout = end($workouts);
                    $lastWorkoutTimestamp = strtotime($lastWorkout["date"]);
                    $nextDayTimestamp = $lastWorkoutTimestamp + (24 * 60 * 60);
                    $lastWorkoutDatePlusOne = date('Y-m-d', $nextDayTimestamp);
                }
                
                $newnum = $_POST["number"];

                if(isset($_POST["date"])){
                    $newex["date"] = DateTime::createFromFormat('Y-m-d', $_POST["date"])->format('Y-m-d');
                    $newex["daily_num"] = intval($_POST["number"]);
                    $newex["reps"] = [array_map('intval', explode("/",$_POST["reps0"])),array_map('intval', explode("/",$_POST["reps1"])),array_map('intval', explode("/",$_POST["reps2"]))];
                    $newex["weight"] = [floatval($_POST["weight0"]), floatval($_POST["weight1"]), floatval($_POST["weight2"])];

                    $exerces = [];
                    foreach($plan["dailys"] as $daily){
                        if ($daily["number"] == $newnum){
                            $exerces = $daily["exercises"];
                        }
                    }
                    foreach($exerces as $key => $exerci){
                        foreach($exercises as $kay => $bro){
                            if($bro["name"] == $exerci){
                                $currentWeight = floatval(str_replace(',', '.', $newex["weight"][$key]));
                                $roundedWeight = round($currentWeight * 4) / 4;
                                $exercises[$kay]["weight"] = $roundedWeight;
                                $exercises[$kay]["lastrep"] = end($newex["reps"][$key]);
                            }
                        }
                    }

                    array_push($workouts, $newex);
                    $bra = array('exercises'=>$exercises);
                    $bro = array('plan'=>$plan);
                    $bre = array('workouts'=>$workouts);
                    $xd = array_merge($bra, $bro, $bre);
                    file_put_contents('../../json/workout.json', json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

                    $lastWorkoutDatePlusOne = date('Y-m-d'); 

                    if (!empty($workouts)) {
                        $lastWorkout = end($workouts);
                        $lastWorkoutTimestamp = strtotime($lastWorkout["date"]);
                        $nextDayTimestamp = $lastWorkoutTimestamp + (24 * 60 * 60);
                        $lastWorkoutDatePlusOne = date('Y-m-d', $nextDayTimestamp);
                    }
                }
                if(end($workouts)["daily_num"] == 6){
                    $newnum = 1;
                }else{
                    $newnum = end($workouts)["daily_num"]+1;
                }
                $bs = "";
                $exerces = [];
                foreach($plan["dailys"] as $daily){
                    if ($daily["number"] == $newnum){
                        $exerces = $daily["exercises"];
                    }
                }
                foreach($exerces as $key => $exerci){
                    foreach($exercises as $kay => $bro){
                        if($bro["name"] == $exerci){
                            $lastrep = $bro["lastrep"];
                            if($lastrep < 5){
                                $newweight = round(($bro["weight"] * 0.9) * 4) / 4;
                            } elseif($lastrep > 10) {
                                $newweight = $bro["weight"]+2*$bro["increase"];
                            } else {
                                $newweight = $bro["weight"]+$bro["increase"];
                            }
                            $bs.="<tr>";
                            $bs.="<td>".$bro["sets"]."x".$bro["reps"]."</td>";
                            $bs.="<td>$exerci</td>";
                            $bs.="<td>$newweight</td>";
                            $bs.="<input type='hidden' name='weight".$key."' value='$newweight'>";
                            $bs.="<td><input required type='text' class='form-control d-inline-block w100' name='reps".$key."' placeholder='5/5/5'></td>";
                            $bs.="</tr>";
                        }
                    }
                }
            }
            eval("?> $nav <?php ");
        ?>
        <div class="container mt-3">
            <nav class="mb-3">
                <div class="nav nav-pills" id="nav-tab" role="tablist">
                    <?php if(Authenticate()){
                        echo'<button class="nav-link" id="nav-workout-tab" data-bs-toggle="tab" data-bs-target="#nav-workout" type="button" role="tab" aria-controls="nav-workout" aria-selected="true">Phraks Greyskull LP</button>';
                    } ?>
                    <button class="nav-link" id="nav-fence-tab" data-bs-toggle="tab" data-bs-target="#nav-fence" type="button" role="tab" aria-controls="nav-fence" aria-selected="false">Beep: Fence</button>
                    <button class="nav-link" id="nav-interval-tab" data-bs-toggle="tab" data-bs-target="#nav-interval" type="button" role="tab" aria-controls="nav-interval" aria-selected="false">Beep: Interval</button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <?php if(Authenticate()){ ?>
                    <div class="tab-pane fade" id="nav-workout" role="tabpanel" aria-labelledby="nav-workout-tab" tabindex="0">
                        <div class="row">
                            <div class="col-12 col-lg-6 mb-3">
                                <h1 class="display-4">Last Workout</h1>
                                <p class="text-black-50">Data of your last workout</p>
                                <div class="rounded bg-dark p-1 my-3"></div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Date</span>
                                            <input readonly type="date" class="form-control form-control-sm" value="<?php echo DateTime::createFromFormat('Y-m-d', end($workouts)["date"])->format('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Number</span>
                                            <input readonly type="number" class="form-control" value="<?php echo end($workouts)["daily_num"]; ?>">
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped table-sm mt-2 mb-0" id="last">
                                    <thead>
                                        <tr>
                                            <th>Reps should</th>
                                            <th>Exercise</th>
                                            <th>Weight</th>
                                            <th>Reps did</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $exerces = [];
                                        foreach($plan["dailys"] as $daily){
                                            if ($daily["number"] == end($workouts)["daily_num"]){
                                                $exerces = $daily["exercises"];
                                            }
                                        }

                                        foreach($exerces as $key => $exerci){
                                            foreach($exercises as $bro){
                                                if($bro["name"] == $exerci){
                                                    echo"<tr>";
                                                    echo"<td>".$bro["sets"]."x".$bro["reps"]."</td>";
                                                }
                                            }
                                            echo"<td>$exerci</td>";
                                            echo"<td>".end($workouts)["weight"][$key]."</td>";
                                            echo"<td>".implode("/",end($workouts)["reps"][$key])."</td>";
                                            echo"</tr>";
                                        }
                                        
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                            <form method="POST" class="col-12 col-lg-6 mb-3">
                                <input type='hidden' name='number' value='<?php echo $newnum; ?>'>
                                <h1 class="display-4">New Workout</h1>
                                <p class="text-black-50">Data of the workout you just did</p>
                                <div class="rounded bg-dark p-1 my-3"></div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Date</span>
                                            <input required name="date" type="date" class="form-control form-control-sm" value="<?php echo(new DateTime())->format('Y-m-d'); ?>" min="<?php echo $lastWorkoutDatePlusOne; ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Number</span>
                                            <input readonly type="number" class="form-control" value="<?php echo $newnum; ?>">
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped table-sm mt-2" id="new">
                                    <thead>
                                        <tr>
                                            <th>Reps should</th>
                                            <th>Exercise</th>
                                            <th>Weight</th>
                                            <th>Reps did</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        echo $bs;
                                    ?>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-success"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
                <div class="tab-pane fade" id="nav-fence" role="tabpanel" aria-labelledby="nav-fence-tab" tabindex="0">
                    <h1 class="display-4">Beep: Fence</h1>
                    <p class="text-black-50">Train fencing with audio feedback</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <form class="row" method="POST" action="javascript:void(0);" id="beepform">
                        <div class="col-6 col-sm-3 col-lg-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="terz" checked>
                                <label class="form-check-label">Terz</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="agtterz">
                                <label class="form-check-label">A. Terz</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="quart" checked>
                                <label class="form-check-label">Quart</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="agtquart">
                                <label class="form-check-label">A. Quart</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sekund" checked>
                                <label class="form-check-label">Sekund</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="agtsekund">
                                <label class="form-check-label">A. Sekund</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="zieher" checked>
                                <label class="form-check-label">Zieher</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="agtzieher">
                                <label class="form-check-label">A. Zieher</label>
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i></span>
                                <input id="duration" required type="number" class="form-control" placeholder="ms" min="500" max="10000">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <button id="beepbtn" class="btn btn-primary w-100" type="submit"><i class="bi bi-soundwave"></i> Alert</button>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade" id="nav-interval" role="tabpanel" aria-labelledby="nav-interval-tab" tabindex="0">
                    <h1 class="display-4">Beep: Interval</h1>
                    <p class="text-black-50">Beep in stacked intervals</p>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <form class="row" method="POST" action="javascript:void(0);" id="intervalform">
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i><sub class="ms125">delay</sub></span>
                                <input id="t_delay" required type="number" class="form-control" placeholder="s" min="1" max="100" value="20">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i><sub class="ms125">1</sub></span>
                                <input id="t_1" required type="number" class="form-control" placeholder="s" min="1" max="100" value="3">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i><sub class="ms125">2</sub></span>
                                <input id="t_2" required type="number" class="form-control" placeholder="s" min="1" max="100" value="1">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i><sub class="ms125">3</sub></span>
                                <input id="t_3" required type="number" class="form-control" placeholder="s" min="1" max="100" value="6">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-stopwatch"></i><sub class="ms125">4</sub></span>
                                <input id="t_4" required type="number" class="form-control" placeholder="s" min="1" max="100" value="20">
                            </div>
                        </div>
                        <div class="col-6 col-lg-4 mb-3 d-flex align-items-center justify-content-center">
                            <button id="intervalbtn" class="btn btn-primary w-100" type="submit"><i class="bi bi-soundwave"></i> Alert</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/datatables.min.js"></script>
        <script>
            $(document).ready( function () {
                if(localStorage.getItem('activeTabWorkout') === null || localStorage.getItem('activeTabWorkout') === "undefined"){
                    localStorage.setItem('activeTabWorkout', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabWorkout', $(this).data('bs-target'));
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabWorkout')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabWorkout') + '-tab').addClass('active');

                $('#last').DataTable({
                    "autoWidth": false,
                    responsive: true,
                    "pageLength": 10,
                    dom: 'rt'
                });
                $('#new').DataTable({
                    "autoWidth": false,
                    responsive: true,
                    "pageLength": 10,
                    dom: 'rt'
                });

                jQuery.fn.submitToggle = function(a, b) {
                    return this.on("submit", function(ev) { [b, a][this.$_io ^= 1].call(this, ev) });
                };

                $("#beepform :checkbox").change(function() {
                    if(this.checked) {
                        let context = new AudioContext();
                        let oscillator = context.createOscillator();
                        oscillator.type = "triangle";
                        switch($(this).attr("id")){
                            case "terz":
                            case "agtterz":
                                oscillator.frequency.value = 100;
                                break;
                            case "quart":
                            case "agtquart":
                                oscillator.frequency.value = 200;
                                break;
                            case "sekund":
                            case "agtsekund":
                                oscillator.frequency.value = 700;
                                break;
                            case "zieher":
                            case "agtzieher":
                                oscillator.frequency.value = 4000;
                                break;
                        }
                        oscillator.connect(context.destination);
                        oscillator.start(); 
                        switch($(this).attr("id")){
                            case "terz":
                            case "quart":
                            case "sekund":
                            case "zieher":
                                setTimeout(function () {
                                    oscillator.stop();
                                }, 100);
                                break;
                            case "agtterz":
                            case "agtquart":
                            case "agtsekund":
                            case "agtzieher":
                                setTimeout(function () {
                                    oscillator.stop();
                                }, 400);
                                break;
                        }
                    }
                });

                var intervole;
                $('#beepform').submitToggle(function(){
                    let dur = $('#duration').val();
                    let checkedarr = $("#beepform input:checkbox:checked").map(function(){
                        return $(this).attr('id');
                    }).get(); 
                    let context = new AudioContext();
                    let timer = setInterval.bind(null, function () {
                        let oscillator = context.createOscillator();
                        oscillator.type = "triangle";
                        let randus = checkedarr[Math.floor(Math.random() * checkedarr.length)];
                        switch(randus){
                            case "terz":
                            case "agtterz":
                                oscillator.frequency.value = 100;
                                break;
                            case "quart":
                            case "agtquart":
                                oscillator.frequency.value = 200;
                                break;
                            case "sekund":
                            case "agtsekund":
                                oscillator.frequency.value = 700;
                                break;
                            case "zieher":
                            case "agtzieher":
                                oscillator.frequency.value = 4000;
                                break;
                        }
                        oscillator.connect(context.destination);
                        oscillator.start(); 
                        switch(randus){
                            case "terz":
                            case "quart":
                            case "sekund":
                            case "zieher":
                                setTimeout(function () {
                                    oscillator.stop();
                                }, 100);
                                break;
                            case "agtterz":
                            case "agtquart":
                            case "agtsekund":
                            case "agtzieher":
                                setTimeout(function () {
                                    oscillator.stop();
                                }, 400);
                                break;
                        }
                    }, dur);
                    intervole = timer();

                    $('#beepbtn').removeClass("btn-primary");
                    $('#beepbtn').addClass("btn-danger");
                    $('#beepbtn').text("Stop");
                }, function(){
                    clearInterval(intervole);

                    $('#beepbtn').removeClass("btn-danger");
                    $('#beepbtn').addClass("btn-primary");
                    $('#beepbtn').text("Start");
                });

                var intervalus;
                var timeoutus = [];
                $('#intervalform').submitToggle(function(){
                    let delay = $('#t_delay').val();
                    let t = [
                        Number($('#t_1').val()),
                        Number($('#t_2').val()),
                        Number($('#t_3').val()),
                        Number($('#t_4').val())
                    ]

                    let context = new AudioContext();

                    let timus=(()=> {
                        let oscillator = context.createOscillator();
                        oscillator.type = "triangle";
                        oscillator.frequency.value = 500;
                        oscillator.connect(context.destination);
                        oscillator.start();
                        setTimeout(function () {
                            oscillator.stop();
                        }, 200);
                    });

                    timeoutus.push(setTimeout(() => {
                        intervalus = setInterval(() => {
                            timus();
                            timeoutus.push(setTimeout(() => {
                                timus();
                                timeoutus.push(setTimeout(() => {
                                    timus();
                                    timeoutus.push(setTimeout(() => {
                                        timus();
                                        timeoutus.push(setTimeout(() => {}, t[3]*1000));
                                    }, t[2]*1000));
                                }, t[1]*1000));
                            }, t[0]*1000));
                        }, (t.reduce((a, b) => a + b, 0)*1000));

                        timus();
                        timeoutus.push(setTimeout(() => {
                            timus();
                            timeoutus.push(setTimeout(() => {
                                timus();
                                timeoutus.push(setTimeout(() => {
                                    timus();
                                    timeoutus.push(setTimeout(() => {}, t[3]*1000));
                                }, t[2]*1000));
                            }, t[1]*1000));
                        }, t[0]*1000));
                    }, delay*1000));
                    
                    $('#intervalbtn').removeClass("btn-primary");
                    $('#intervalbtn').addClass("btn-danger");
                    $('#intervalbtn').text("Stop");
                }, function(){
                    timeoutus.forEach(out => {
                        clearInterval(out);
                    });
                    clearInterval(intervalus);

                    $('#intervalbtn').removeClass("btn-danger");
                    $('#intervalbtn').addClass("btn-primary");
                    $('#intervalbtn').text("Start");
                });
            });
        </script>
    </body>
</html>