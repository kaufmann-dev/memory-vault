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
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $weight = json_decode(file_get_contents('../../json/weight.json'), true)['weight'];
            $bloodlevels = json_decode(file_get_contents('../../json/bloodlevels.json'), true)['levels'];
            $hormones = json_decode(file_get_contents('../../json/hormones.json'), true)['levels'];
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
                if(isset($_POST["newweight"])){
                    $newweight["date"] = (new DateTime())->format('Y-m-d H:i');
                    $newweight["weight"] = floatval($_POST["newweight"]);
                    array_push($weight, $newweight);
                    $gringo = array('weight'=>$weight);
                    file_put_contents('../../json/weight.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["newdia"]) && isset($_POST["newsys"]) && isset($_POST["newpul"])){
                    $newblood["date"] = (new DateTime())->format('Y-m-d H:i');
                    $newblood["dia"] = floatval($_POST["newdia"]);
                    $newblood["sys"] = floatval($_POST["newsys"]);
                    $newblood["pul"] = floatval($_POST["newpul"]);
                    array_push($bloodlevels, $newblood);
                    $gringo = array('levels'=>$bloodlevels);
                    file_put_contents('../../json/bloodlevels.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["h_date"]) && (isset($_POST["h_lh"]) || isset($_POST["h_fsh"]) || isset($_POST["h_e2"]) || isset($_POST["h_prog"]) || isset($_POST["h_prl"]) || isset($_POST["h_t"]) || isset($_POST["h_bat"]) || isset($_POST["h_shbg"]) || isset($_POST["h_tsh"]))){
                    $newhormones["date"] = $_POST["h_date"];
                    if(!empty($_POST["h_lh"])){ $newhormones["lh"] = floatval($_POST["h_lh"]); } else { $newhormones["lh"] = null; }
                    if(!empty($_POST["h_fsh"])){ $newhormones["fsh"] = floatval($_POST["h_fsh"]); } else { $newhormones["fsh"] = null; }
                    if(!empty($_POST["h_e2"])){ $newhormones["e2"] = floatval($_POST["h_e2"]); } else { $newhormones["e2"] = null; }
                    if(!empty($_POST["h_prog"])){ $newhormones["prog"] = floatval($_POST["h_prog"]); } else { $newhormones["prog"] = null; }
                    if(!empty($_POST["h_prl"])){ $newhormones["prl"] = floatval($_POST["h_prl"]); } else { $newhormones["prl"] = null; }
                    if(!empty($_POST["h_t"])){ $newhormones["t"] = floatval($_POST["h_t"]); } else { $newhormones["t"] = null; }
                    if(!empty($_POST["h_bat"])){ $newhormones["bat"] = floatval($_POST["h_bat"]); } else { $newhormones["bat"] = null; }
                    if(!empty($_POST["h_shbg"])){ $newhormones["shbg"] = floatval($_POST["h_shbg"]); } else { $newhormones["shbg"] = null; }
                    if(!empty($_POST["h_tsh"])){ $newhormones["tsh"] = floatval($_POST["h_tsh"]); } else { $newhormones["tsh"] = null; }
                    array_push($hormones, $newhormones);
                    $gringo = array('levels'=>$hormones);
                    file_put_contents('../../json/hormones.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }
            
            eval("?> $nav <?php ");
        ?>
        <div class="container my-3">
            <?php
                if(!Authenticate()){
                    echo"You need to be logged in to access this page.";
                } else {
                ?>
                <nav class="mb-3">
                    <div class="nav nav-pills" id="nav-tab" role="tablist">
                        <button class="nav-link" id="nav-weight-tab" data-bs-toggle="tab" data-bs-target="#nav-weight" type="button" role="tab" aria-controls="nav-weight" aria-selected="true">Gewicht</button>
                        <button class="nav-link" id="nav-hormones-tab" data-bs-toggle="tab" data-bs-target="#nav-hormones" type="button" role="tab" aria-controls="nav-hormones" aria-selected="false">Hormone</button>
                        <button class="nav-link" id="nav-blood-tab" data-bs-toggle="tab" data-bs-target="#nav-blood" type="button" role="tab" aria-controls="nav-blood" aria-selected="false">Blut</button>
                        <button class="nav-link" id="nav-stimulants-tab" data-bs-toggle="tab" data-bs-target="#nav-stimulants" type="button" role="tab" aria-controls="nav-stimulants" aria-selected="false">Lebenszeiten</button>
                    </div>
                </nav>
                <div class="tab-content" id="nav-tabContent">
                    <div class="tab-pane fade" id="nav-weight" role="tabpanel" aria-labelledby="nav-weight-tab" tabindex="0">
                    <h1 class="display-4">Gewicht</h1>
                        <p class="text-black-50">Der Verlauf meines Körpergewichtes</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div class="chartcontainer h60vh mb-3">
                            <canvas id="weightChart"></canvas>
                        </div>
                        <form method="POST" class="row">
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Gewicht</span>
                                    <input step="0.01" min="0" class="form-control" required type="number" name="newweight" placeholder="kg">
                                </div>
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="nav-hormones" role="tabpanel" aria-labelledby="nav-hormones-tab" tabindex="0">
                    <h1 class="display-4">Hormone</h1>
                        <p class="text-black-50">Der Verlauf meiner Hormonwerte</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div class="chartcontainer h60vh mb-3">
                            <canvas id="hormonesChart"></canvas>
                        </div>
                        <form method="POST" class="row">
                            <div class="col-12 col-sm-6 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Date</span>
                                    <input name="h_date" required type="date" class="form-control form-control-sm" value="<?php echo(new DateTime())->format('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">LH</span>
                                    <input type="number" class="form-control" placeholder="mU/ml" min="0" step="0.001" name="h_lh">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">FSH</span>
                                    <input type="number" class="form-control" placeholder="mU/ml" min="0" step="0.001" name="h_fsh">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">E2</span>
                                    <input type="number" class="form-control" placeholder="pg/ml" min="0" step="0.001" name="h_e2">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">PROG</span>
                                    <input type="number" class="form-control" placeholder="ng/ml" min="0" step="0.001" name="h_prog">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">PRL</span>
                                    <input type="number" class="form-control" placeholder="ng/ml" min="0" step="0.001" name="h_prl">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">T</span>
                                    <input type="number" class="form-control" placeholder="ng/ml" min="0" step="0.001" name="h_t">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">BAT</span>
                                    <input type="number" class="form-control" placeholder="ng/ml" min="0" step="0.001" name="h_bat">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">SHBG</span>
                                    <input type="number" class="form-control" placeholder="nmol/l" min="0" step="0.001" name="h_shbg">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">TSH</span>
                                    <input type="number" class="form-control" placeholder="µU/ml" min="0" step="0.001" name="h_tsh">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="nav-blood" role="tabpanel" aria-labelledby="nav-blood-tab" tabindex="0">
                    <h1 class="display-4">Blut</h1>
                        <p class="text-black-50">Der Verlauf meines Blutdrucks und Pulses</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div class="chartcontainer h60vh mb-3">
                            <canvas id="bloodChart"></canvas>
                        </div>
                        <form method="POST" class="row">
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">SYS</span>
                                    <input type="number" class="form-control" placeholder="mmHg" min="0" step="0.1" required name="newsys">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">DIA</span>
                                    <input type="number" class="form-control" placeholder="mmHg" min="0" step="0.1" required name="newdia">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">PUL</span>
                                    <input type="number" class="form-control" placeholder="/min" min="0" step="0.1" required name="newpul">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 mb-2">
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="nav-stimulants" role="tabpanel" aria-labelledby="nav-stimulants-tab" tabindex="0">
                        <h1 class="display-4">Lebenszeiten</h1>
                        <p class="text-black-50">Die Lebenszeiten verschiedener Substanzen (immer immediate-release IR)</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div class="chartcontainer h60vh mb-3">
                            <canvas id="s489Chart"></canvas>
                        </div>
                    </div>
                </div>
                <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/chart.min.js"></script>
        <script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
        <script src="/js/chartjs-plugin-annotation.min.js"></script>
        <script>
            $(document).ready( function () {
                if(localStorage.getItem('activeTabDiagrams') === null || localStorage.getItem('activeTabDiagrams') === "undefined"){
                    localStorage.setItem('activeTabDiagrams', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabDiagrams', $(this).data('bs-target'));
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabDiagrams')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabDiagrams') + '-tab').addClass('active');

                <?php
                    if(Authenticate()) {
                        ?>
                            var ctx1 = document.getElementById("weightChart").getContext("2d");
                            var ctx2 = document.getElementById("hormonesChart").getContext("2d");
                            var ctx489 = document.getElementById("s489Chart").getContext("2d");
                            var ctxblood = document.getElementById("bloodChart").getContext("2d");

                            var weight = <?php echo json_encode($weight); ?>;
                            var hormones = <?php echo json_encode($hormones); ?>;
                            var bloodlevels = <?php echo json_encode($bloodlevels); ?>;

                            var weightDates = [], weights = [];
                            var hormoneDates = [], lh = [], fsh = [], e2 = [], prog = [], prl = [], t = [], bat = [], shbg = [], tsh = [];
                            var bloodDates = [], sys = [], dia = [], pul = [];

                            weight.forEach((element) => {
                                weightDates.push(element["date"]);
                                weights.push(element["weight"]);
                            });

                            hormones.forEach((element) => {
                                hormoneDates.push(element["date"]);
                                lh.push(element["lh"]);
                                fsh.push(element["fsh"]);
                                if(!element["e2"]){e2.push(null)}else{e2.push(element["e2"]/10)}
                                if(!element["prog"]){prog.push(null)}else{prog.push(element["prog"]*10)}
                                if(!element["prl"]){prl.push(null)}else{prl.push(element["prl"]/10)}
                                t.push(element["t"]);
                                bat.push(element["bat"]);
                                if(!element["shbg"]){shbg.push(null)}else{shbg.push(element["shbg"]/10)}
                                tsh.push(element["tsh"]);
                            });

                            bloodlevels.forEach((element) => {
                                bloodDates.push(element["date"]);
                                sys.push(element["sys"]);
                                dia.push(element["dia"]);
                                pul.push(element["pul"]);
                            });

                            var weightChart = new Chart(ctx1, {
                                type: 'line',
                                options: {
                                    maintainAspectRatio: false,
                                    scales: {
                                        xAxis: {
                                            type: 'time',
                                            time: {
                                                parser: 'yyyy-MM-dd HH:mm',
                                                tooltipFormat: 'LLL d HH:mm, y',
                                                unit: 'month',
                                                unitStepSize: 1,
                                                displayFormats: {
                                                    'month': 'MMM yy'
                                                }
                                            }
                                        }
                                    }
                                },
                                data: {
                                    labels: weightDates,
                                    datasets: [{
                                        label: 'Gewicht (kg)',
                                        data: weights,
                                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                        borderColor: 'rgba(255,99,132,1)',
                                        borderWidth: 1.5,
                                        tension: 0,
                                        fill: true
                                    }]
                                }
                            });

                            var hormoneChart = new Chart(ctx2, {
                                type: 'line',
                                options: {
                                    maintainAspectRatio: false,
                                    spanGaps: true,
                                    scales: {
                                        xAxis: {
                                            min: '2021-07-09',
                                            max: '<?php echo date("Y-m-d", strtotime("+1 week")); ?>',
                                            type: 'time',
                                            time: {
                                                parser: 'yyyy-MM-dd',
                                                tooltipFormat: 'LLL d, y',
                                                unit: 'month',
                                                unitStepSize: 1,
                                                displayFormats: {
                                                    'month': 'MMM yy'
                                                }
                                            }
                                        },
                                        yAxis: {
                                            min: 0,
                                            max: 9
                                        }
                                    },
                                    plugins: {
                                        annotation: {
                                            annotations: {
                                                box1:{
                                                    type: 'box',
                                                    xMin: '2022-04-15',
                                                    xMax: '2022-10-06',
                                                    yMin: 0,
                                                    yMax: 90,
                                                    backgroundColor: 'rgba(0, 0, 0, 0.05)',
                                                    borderColor: 'rgba(0, 0, 0, 0)'
                                                },
                                                box2:{
                                                    type: 'box',
                                                    xMin: '2023-01-11',
                                                    xMax: '<?php echo date("Y-m-d"); ?>',
                                                    yMin: 0,
                                                    yMax: 90,
                                                    backgroundColor: 'rgba(0, 0, 0, 0.05)',
                                                    borderColor: 'rgba(0, 0, 0, 0)'
                                                },
                                                line1: {
                                                    type: 'line',
                                                    xMin: '2021-07-16',
                                                    xMax: '2021-07-16',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line2: {
                                                    type: 'line',
                                                    xMin: '2021-08-09',
                                                    xMax: '2021-08-09',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line3: {
                                                    type: 'line',
                                                    xMin: '2021-08-18',
                                                    xMax: '2021-08-18',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line4: {
                                                    type: 'line',
                                                    xMin: '2021-09-02',
                                                    xMax: '2021-09-02',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line5: {
                                                    type: 'line',
                                                    xMin: '2021-10-11',
                                                    xMax: '2021-10-11',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line6: {
                                                    type: 'line',
                                                    xMin: '2022-01-27',
                                                    xMax: '2022-01-27',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line7: {
                                                    type: 'line',
                                                    xMin: '2022-07-27',
                                                    xMax: '2022-07-27',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line8: {
                                                    type: 'line',
                                                    xMin: '2022-10-3',
                                                    xMax: '2022-10-3',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line9: {
                                                    type: 'line',
                                                    xMin: '2022-10-7',
                                                    xMax: '2022-10-7',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                },
                                                line10: {
                                                    type: 'line',
                                                    xMin: '2023-01-10',
                                                    xMax: '2023-01-10',
                                                    yMin: 0,
                                                    yMax: 0.5,
                                                    borderColor: 'rgba(0, 0, 0, 1)',
                                                    borderWidth: 3,
                                                }
                                            }
                                        },
                                        legend: {
                                            display: true,
                                            position: 'top',
                                            labels: {
                                                usePointStyle: true,
                                                pointStyle: 'rectRounded',
                                                boxWidth: 10,
                                                padding: 20
                                            }
                                        }
                                    }
                                },
                                data: {
                                    labels: hormoneDates,
                                    datasets: [{
                                        label: 'LH (mU/ml)',
                                        data: lh,
                                        fill: false,
                                        borderColor: '#e3342f',
                                        backgroundColor: 'rgba(227, 52, 47, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'FSH (mU/ml)',
                                        data: fsh,
                                        fill: false,
                                        borderColor: '#f6993f',
                                        backgroundColor: 'rgba(246, 153, 63, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'E2 (pg/ml/10)',
                                        data: e2,
                                        fill: false,
                                        borderColor: '#ffed4a',
                                        backgroundColor: 'rgba(255, 237, 74, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'PROG (ng/ml*10)',
                                        data: prog,
                                        fill: false,
                                        borderColor: '#38c172',
                                        backgroundColor: 'rgba(56, 193, 114, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'PRL (ng/ml/10)',
                                        data: prl,
                                        fill: false,
                                        borderColor: '#4dc0b5',
                                        backgroundColor: 'rgba(77, 192, 181, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'T (ng/ml)',
                                        data: t,
                                        fill: false,
                                        borderColor: '#3490dc',
                                        backgroundColor: 'rgba(52, 144, 220, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'BAT (ng/ml)',
                                        data: bat,
                                        fill: false,
                                        borderColor: '#6574cd',
                                        backgroundColor: 'rgba(101, 116, 205, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'SHBG (nmol/l/10)',
                                        data: shbg,
                                        fill: false,
                                        borderColor: '#9561e2',
                                        backgroundColor: 'rgba(149, 97, 226, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    },{
                                        label: 'TSH (µU/ml)',
                                        data: tsh,
                                        fill: false,
                                        borderColor: '#f66d9b',
                                        backgroundColor: 'rgba(246, 109, 155, 1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        pointRadius: 2,
                                        pointHitRadius: 10,
                                        pointHoverRadius: 7
                                    }]
                                }
                            });

                            var s489Chart = new Chart(ctx489, {
                                type: 'line',
                                options: {
                                    maintainAspectRatio: false,
                                    spanGaps: true,
                                    scales: {
                                        xAxis: {
                                            type: 'time',
                                            time: {
                                                parser: 'HH:mm',
                                                tooltipFormat: 'HH:mm',
                                                unit: 'hour',
                                                unitStepSize: 1,
                                                displayFormats: {
                                                    'hour': 'H'
                                                }
                                            }
                                        }
                                    }
                                },
                                data: {
                                    labels: ["00:00", "00:15", "00:25", "00:30", "00:35", "00:45", "00:55", "01:00", "01:05", "01:30", "02:00", "02:30", "03:00", "03:30", "04:00", "05:00", "06:00", "08:00", "10:00", "12:00", "14:00", "16:00", "17:00", "20:00", "23:59"],
                                    datasets: [{
                                        label: '100mg LDX (Plasma, ng/ml)',
                                        data: [0, null, null, null, null, null, null, 5.16, null, 21.59, 48.19, 73.97, 93.03, 108.72, 114.69, 116.86, 109.54, 98.11, 85.11, 73.37, null, null, null, null, 29.07],
                                        fill: false,
                                        borderColor: '#e3342f',
                                        backgroundColor: 'rgba(227, 52, 47, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    },{
                                        label: '20mg*17 MPH (Plasma, ng/ml)',
                                        data: [0, null, null, 19.975, null, null, null, 94.35, null, 117.725, 107.95, 97.75, 82.875, 69.7, 62.475, 45.9, 34, 20.825, 12.325, 8.075, 5.1, null, 2.55, 1.275, 0.425],
                                        fill: false,
                                        borderColor: '#3490dc',
                                        backgroundColor: 'rgba(52, 144, 220, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    },{
                                        label: '10mg ZLP (Plasma, ng/ml)',
                                        data: [0, 3.86, null, 63.51, null, 105.79, null, 114.39, null, /*109.91*/null, 115.79, 97.72, 89.91, 73.75, 62.98, 46.67, 34.74, 20.04, null, null, null, null, null, null, null, null],
                                        fill: false,
                                        borderColor: '#38c172',
                                        backgroundColor: 'rgba(56, 193, 114, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    },{
                                        label: '200mg/29 Caffeine (Plasma, ng/ml)',
                                        data: [0, 7.03, 20.42, null, 60.26, 87.04, 102.11, null, 108.14, 114.16, 118.18, null, 116.5, null, 100.44, null, 79.01, 65.28, null, 34.82, null, 18.41, null, null, 8.7],
                                        fill: false,
                                        borderColor: '#ec36f5',
                                        backgroundColor: 'rgba(235, 53, 244, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    }, {
                                        label: '3mg*9 Alprazolam (Plasma, ng/ml)',
                                        data: [0, 14.28, null, 64.90, null, 98.22, null, 107.31, null, null, 118.13, null, 113.8, null, 111.63, null, 101.68, 99.95, null, 91.73, null, null, null, null, 52.36],
                                        fill: false,
                                        borderColor: '#f0d400',
                                        backgroundColor: 'rgba(240, 211, 0, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    }, {
                                        label: '4mg*54 Guanfacine XR (Plasma, ng/ml)',
                                        data: [0, null, null, 16.83, null, null, null, 40.88, null, 63.97, 78.88, null, 98.60, null, 110.62, null, 118.80, 115.43, null, 101.00, null, null, null, null, 75.99],
                                        fill: false,
                                        borderColor: '#00d4b9',
                                        backgroundColor: 'rgba(0, 212, 185, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0.4
                                    }]
                                }
                            });

                            var bloodChart = new Chart(ctxblood, {
                                type: 'line',
                                options: {
                                    maintainAspectRatio: false,
                                    scales: {
                                        xAxis: {
                                            type: 'time',
                                            time: {
                                                parser: 'yyyy-MM-dd HH:mm',
                                                tooltipFormat: 'LLL d HH:mm, y',
                                                unit: 'month',
                                                unitStepSize: 1,
                                                displayFormats: {
                                                    'month': 'MMM yy'
                                                }
                                            }
                                        }
                                    }
                                },
                                data: {
                                    labels: bloodDates,
                                    datasets: [{
                                        label: 'SYS',
                                        data: sys,
                                        borderColor: '#3490dc',
                                        backgroundColor: 'rgba(52, 144, 220, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0,
                                        fill: false
                                    },{
                                        label: 'DIA',
                                        data: dia,
                                        borderColor: '#38c172',
                                        backgroundColor: 'rgba(56, 193, 114, 0.2)',
                                        borderWidth: 1.5,
                                        tension: 0,
                                        fill: false
                                    },{
                                        label: 'PUL',
                                        data: pul,
                                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                        borderColor: 'rgba(255,99,132,1)',
                                        borderWidth: 1.5,
                                        tension: 0,
                                        fill: false
                                    }]
                                }
                            });
                        <?php } ?>
            } );
        </script>
    </body>
</html>