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
        <link rel="stylesheet" type="text/css" href="/css/datatables.min.css"/>
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $inventory = json_decode(file_get_contents('../../json/inventory.json'), true)['items'];
            $meds = json_decode(file_get_contents('../../json/meds.json'), true)['items'];
            $daily = json_decode(file_get_contents('../../json/inventory.json'), true)['daily'];
            $daycounters = json_decode(file_get_contents('../../json/daycounters.json'), true)['counters'];
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
                if(isset($_POST["counterreset"])){
                    foreach($daycounters as $key => $field){
                        if($field["id"] == $_POST["counteritem"]){
                            $daycounters[$key]['initiated'] = (new DateTime())->format('Y-m-d');
                        }
                    }
                    $dayc0unters["counters"] = $daycounters;
                    file_put_contents('../../json/daycounters.json',json_encode($dayc0unters, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["daily"])){
                    foreach ($inventory as $key => $field) {
                        if($field['daily'] > 0){
                            $inventory[$key]['have'] = $inventory[$key]['have'] - $field['daily'];
                        }
                    }
                    
                    $dailyy = new DateTime();
                    $bro = array('daily'=>$dailyy->format('Y-m-d H:i'));
                    $bre = array('items'=>$inventory);
                    $xd = array_merge($bro, $bre);

                    file_put_contents('../../json/inventory.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["item"])){
                    if(isset($_POST["minus"])){
                        foreach ($inventory as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $inventory[$key]['have'] = $inventory[$key]['have'] - 1;
                            }
                        }
                    } elseif(isset($_POST["plus"])){
                        foreach ($inventory as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $inventory[$key]['have'] = $inventory[$key]['have'] + 1;
                            }
                        }
                    } elseif(isset($_POST["newvalue"])){
                        foreach ($inventory as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $inventory[$key]['have'] = $_POST["newvalue"];
                            }
                        }
                    }
                    
                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$inventory);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/inventory.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["inv_create_name"]) && isset($_POST["inv_create_need"]) && isset($_POST["inv_create_have"]) && isset($_POST["inv_create_unit"])){
                    $newinv["id"] = 0;
                    foreach($inventory as $inv){
                        if($inv["id"] > $newinv["id"]){
                            $newinv["id"] = $inv["id"];
                        }
                    }
                    $newinv["id"]++;
                    $newinv["name"] = $_POST["inv_create_name"];
                    $newinv["need"] = intval($_POST["inv_create_need"]);
                    $newinv["have"] = intval($_POST["inv_create_have"]);
                    $newinv["unit"] = $_POST["inv_create_unit"];
                    if(isset($_POST["inv_create_daily"])){
                        $newinv["daily"] = intval($_POST["inv_create_daily"]);
                    } else {
                        $newinv["daily"] = 0;
                    }
                    array_push($inventory, $newinv);

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$inventory);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/inventory.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } 
                if(isset($_POST["inv_edit_name"]) && isset($_POST["inv_edit_need"]) && isset($_POST["inv_edit_have"]) && isset($_POST["inv_edit_unit"]) && isset($_POST["inv_edit_daily"])){
                    $newinv["id"] = intval($_POST["inv_edit_id"]);
                    $newinv["name"] = $_POST["inv_edit_name"];
                    $newinv["need"] = intval($_POST["inv_edit_need"]);
                    $newinv["have"] = intval($_POST["inv_edit_have"]);
                    $newinv["unit"] = $_POST["inv_edit_unit"];
                    $newinv["daily"] = intval($_POST["inv_edit_daily"]);

                    foreach ($inventory as $key => $field) {
                        if ($field['id'] == $newinv["id"]) {
                            $inventory[$key] = $newinv;
                        }
                    }

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$inventory);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/inventory.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["inv_delete"])){
                    foreach ($inventory as $key => $field) {
                        if ($field['id'] == $_POST["inv_delete"]) {
                            unset($inventory[$key]);
                        }
                    }

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$inventory);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/inventory.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["dailymeds"])){
                    foreach ($meds as $key => $field) {
                        if($field['daily'] > 0){
                            $meds[$key]['have'] = $meds[$key]['have'] - $field['daily'];
                        }
                    }
                    
                    $dailyy = new DateTime();
                    $bro = array('daily'=>$dailyy->format('Y-m-d H:i'));
                    $bre = array('items'=>$meds);
                    $xd = array_merge($bro, $bre);

                    file_put_contents('../../json/meds.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["item"])){
                    if(isset($_POST["minusmeds"])){
                        foreach ($meds as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $meds[$key]['have'] = $meds[$key]['have'] - 1;
                            }
                        }
                    } elseif(isset($_POST["plusmeds"])){
                        foreach ($meds as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $meds[$key]['have'] = $meds[$key]['have'] + 1;
                            }
                        }
                    } elseif(isset($_POST["newvaluemeds"])){
                        foreach ($meds as $key => $field) {
                            if ($field['id'] == $_POST["item"]) {
                                $meds[$key]['have'] = $_POST["newvaluemeds"];
                            }
                        }
                    }
                    
                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$meds);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/meds.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["meds_create_name"]) && isset($_POST["meds_create_need"]) && isset($_POST["meds_create_have"]) && isset($_POST["meds_create_unit"])){
                    $newinv["id"] = 0;
                    foreach($meds as $inv){
                        if($inv["id"] > $newinv["id"]){
                            $newinv["id"] = $inv["id"];
                        }
                    }
                    $newinv["id"]++;
                    $newinv["name"] = $_POST["meds_create_name"];
                    $newinv["need"] = floatval($_POST["meds_create_need"]);
                    $newinv["have"] = floatval($_POST["meds_create_have"]);
                    $newinv["unit"] = $_POST["meds_create_unit"];
                    if(isset($_POST["meds_create_daily"])){
                        $newinv["daily"] = floatval($_POST["meds_create_daily"]);
                    } else {
                        $newinv["daily"] = 0;
                    }
                    array_push($meds, $newinv);

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$meds);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/meds.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } 
                if(isset($_POST["meds_edit_name"]) && isset($_POST["meds_edit_need"]) && isset($_POST["meds_edit_have"]) && isset($_POST["meds_edit_unit"]) && isset($_POST["meds_edit_daily"])){
                    $newinv["id"] = intval($_POST["meds_edit_id"]);
                    $newinv["name"] = $_POST["meds_edit_name"];
                    $newinv["need"] = floatval($_POST["meds_edit_need"]);
                    $newinv["have"] = floatval($_POST["meds_edit_have"]);
                    $newinv["unit"] = $_POST["meds_edit_unit"];
                    $newinv["daily"] = floatval($_POST["meds_edit_daily"]);

                    foreach ($meds as $key => $field) {
                        if ($field['id'] == $newinv["id"]) {
                            $meds[$key] = $newinv;
                        }
                    }

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$meds);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/meds.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["meds_delete"])){
                    foreach ($meds as $key => $field) {
                        if ($field['id'] == $_POST["meds_delete"]) {
                            unset($meds[$key]);
                        }
                    }

                    $bro = array('daily'=>$daily);
                    $bre = array('items'=>$meds);
                    $xd = array_merge($bro, $bre);
                    file_put_contents('../../json/meds.json',json_encode($xd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["counter_create_name"]) && isset($_POST["counter_create_initiated"])){
                    $newinv["id"] = 0;
                    foreach($daycounters as $inv){
                        if($inv["id"] > $newinv["id"]){
                            $newinv["id"] = $inv["id"];
                        }
                    }
                    $newinv["id"]++;
                    $newinv["name"] = $_POST["counter_create_name"];
                    $newinv["initiated"] = $_POST["counter_create_initiated"];
                    $newinv["max_days"] = null;
                    array_push($daycounters, $newinv);

                    $gringo = array('counters'=>$daycounters);
                    file_put_contents('../../json/daycounters.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["counter_edit_name"]) && isset($_POST["counter_edit_initiated"])){
                    $newinv["id"] = intval($_POST["counter_edit_id"]);
                    $newinv["name"] = $_POST["counter_edit_name"];
                    $newinv["initiated"] = $_POST["counter_edit_initiated"];
                    $newinv["max_days"] = null;

                    foreach ($daycounters as $key => $field) {
                        if ($field['id'] == $newinv["id"]) {
                            $daycounters[$key] = $newinv;
                        }
                    }

                    $gringo = array('counters'=>$daycounters);
                    file_put_contents('../../json/daycounters.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["counter_delete"])){
                    foreach ($daycounters as $key => $field) {
                        if ($field['id'] == $_POST["counter_delete"]) {
                            unset($daycounters[$key]);
                        }
                    }

                    $gringo = array('counters'=>$daycounters);
                    file_put_contents('../../json/daycounters.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }
            
            eval("?> $nav <?php ");
        ?>
        <div class="container my-3">
            <?php if(!Authenticate()){
                echo"You need to be logged in to access this page.";
            } else { ?>
                <nav class="mb-3">
                    <div class="nav nav-pills" id="nav-tab" role="tablist">
                        <button class="nav-link" id="nav-daycounter-tab" data-bs-toggle="tab" data-bs-target="#nav-daycounter" type="button" role="tab" aria-controls="nav-daycounter" aria-selected="true">Tageszähler</button>
                        <button disabled class="nav-link" id="nav-vorrat-tab" data-bs-toggle="tab" data-bs-target="#nav-vorrat" type="button" role="tab" aria-controls="nav-vorrat" aria-selected="true">Vorratskontrolle</button>
                        <button disabled class="nav-link" id="nav-meds-tab" data-bs-toggle="tab" data-bs-target="#nav-meds" type="button" role="tab" aria-controls="nav-meds" aria-selected="true">Medikamentenvorrat</button>
                        <button disabled class="nav-link" id="nav-4ve23-tab" data-bs-toggle="tab" data-bs-target="#nav-4ve23" type="button" role="tab" aria-controls="nav-4ve23" aria-selected="true">4-VE2-3</button>
                        <button disabled class="nav-link" id="nav-4ve21-tab" data-bs-toggle="tab" data-bs-target="#nav-4ve21" type="button" role="tab" aria-controls="nav-4ve21" aria-selected="false">4-VE2-1</button>
                    </div>
                </nav>
                <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade" id="nav-daycounter" role="tabpanel" aria-labelledby="nav-daycounter-tab" tabindex="0">
                        <h1 class="display-4">Tageszähler</h1>
                        <p class="text-black-50">Zählt Tage die seit einem Ereignis vergangen sind</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <form method="post" id="counter_create" class="row mb-3">
                            <div class="mb-2 col-12 col-sm-5 ">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Initiated</span>
                                    <input name="counter_create_initiated" required type="date" class="form-control" value="<?php echo(new DateTime())->format('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-5 ">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Name</span>
                                    <input type="text" class="form-control" name="counter_create_name" required>
                                </div>
                            </div>
                            <div class="col-6 col-sm-2">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                        <?php if(isset($_POST["counter_edit"])){
                            foreach($daycounters as $counter){
                                if($counter["id"] == $_POST["counter_edit"]){
                                    $s_counter = $counter;
                                }
                            }
                            ?> <form method="post" id="counter_edit" class="row mb-3">
                                <input type="hidden" name="counter_edit_id" value="<?php echo $s_counter["id"]; ?>" required>
                                <div class="mb-2 col-12 col-sm-5 ">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Initiated</span>
                                        <input value="<?php echo $s_counter["initiated"]; ?>" name="counter_edit_initiated" required type="date" class="form-control" value="<?php echo(new DateTime())->format('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="mb-2 col-8 col-sm-5 ">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Name</span>
                                        <input value="<?php echo $s_counter["name"]; ?>" type="text" class="form-control" name="counter_edit_name" required>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-2">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-arrow-repeat"></i> Update</button>
                                </div>
                            </form>
                        <?php } ?>
                        <table class="table table-striped" id="dailycounters">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Objekt</th>
                                    <th>Verstrichene Zeit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($daycounters as $lol){
                                    echo"<tr class='text-break'>";
                                        echo"<td>".$lol["id"]."</td>";
                                        echo"<td>".$lol["name"]."</td>";
                                        $dt1 = new DateTime();
                                        $dt2 = DateTime::createFromFormat('Y-m-d', $lol["initiated"]);
                                        echo"<td>".date_diff($dt1, $dt2)->format("%a")." Tage</td>";
                                    echo"</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="nav-vorrat" role="tabpanel" aria-labelledby="nav-vorrat-tab" tabindex="0">
                        <h1 class="display-4">Vorratskontrolle</h1>
                        <p class="text-black-50">Überprüft die Dringlichkeit der Neubeschaffung eines Gegenstandes</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <form method="post" id="inv_create" class="row mb-3">
                            <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Name</span>
                                    <input type="text" class="form-control" name="inv_create_name" required>
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Unit</span>
                                    <input type="text" class="form-control" name="inv_create_unit" required>
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Need</span>
                                    <input type="number" class="form-control" name="inv_create_need" required min="0">
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Have</span>
                                    <input type="number" class="form-control" name="inv_create_have" required min="0">
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Daily</span>
                                    <input type="number" class="form-control" name="inv_create_daily" min="0">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                        <?php if(isset($_POST["inv_edit"])){
                            foreach($inventory as $inv){
                                if($inv["id"] == $_POST["inv_edit"]){
                                    $s_inv = $inv;
                                }
                            }
                            ?> <form method="post" id="inv_edit" class="row mb-3">
                                <input type="hidden" name="inv_edit_id" value="<?php echo $s_inv["id"]; ?>" required>
                                <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Name</span>
                                        <input value="<?php echo $s_inv["name"]; ?>" type="text" class="form-control" name="inv_edit_name" required>
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Unit</span>
                                        <input value="<?php echo $s_inv["unit"]; ?>" type="text" class="form-control" name="inv_edit_unit" required>
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Need</span>
                                        <input value="<?php echo $s_inv["need"]; ?>" type="number" class="form-control" name="inv_edit_need" required min="0">
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Have</span>
                                        <input value="<?php echo $s_inv["have"]; ?>" type="number" class="form-control" name="inv_edit_have" required min="0">
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Daily</span>
                                        <input value="<?php echo $s_inv["daily"]; ?>" type="number" class="form-control" name="inv_edit_daily" min="0" required>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-arrow-repeat"></i> Update</button>
                                </div>
                            </form>
                        <?php } ?>
                        <table class="table table-striped" id="inventory">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Objekt</th>
                                    <th>Sollte</th>
                                    <th>Ist</th>
                                    <th>Daily</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($inventory as $lol){
                                    echo"<tr class='text-break'>";
                                        echo"<td>".$lol["id"]."</td>";
                                        echo"<td>".$lol["name"]."</td>";
                                        echo"<td><b>".$lol["need"]."</b> ".$lol["unit"]."</td>";
                                        echo"<td><b>".$lol["have"]."</b> ".$lol["unit"]."</td>";
                                        if($lol["daily"] > 0){
                                            echo"<td>".$lol["daily"]."</td>";
                                        } else{
                                            echo"<td>-</td>";
                                        }
                                        if($lol["have"] < $lol["need"]){
                                            echo"<td><p class='text-danger m-0'>Ersetzen</p></td>";
                                        } else{
                                            echo"<td><p class='text-success m-0'>Okay</p></td>";
                                        }
                                    echo"</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="nav-meds" role="tabpanel" aria-labelledby="nav-meds-tab" tabindex="0">
                        <h1 class="display-4">Medikamentenvorrat</h1>
                        <p class="text-black-50">Überprüft die Dringlichkeit der Neubeschaffung von Medikamenten</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <form method="post" id="meds_create" class="row mb-3">
                            <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Name</span>
                                    <input type="text" class="form-control" name="meds_create_name" required>
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Unit</span>
                                    <input type="text" class="form-control" name="meds_create_unit" required>
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Need</span>
                                    <input type="number" step=0.01 class="form-control" name="meds_create_need" required min="0">
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Have</span>
                                    <input type="number" step=0.01 class="form-control" name="meds_create_have" required min="0">
                                </div>
                            </div>
                            <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Daily</span>
                                    <input type="number" step=0.01 class="form-control" name="meds_create_daily" min="0">
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 col-xl-2 col-xxl-3">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Add</button>
                            </div>
                        </form>
                        <?php if(isset($_POST["meds_edit"])){
                            foreach($meds as $inv){
                                if($inv["id"] == $_POST["meds_edit"]){
                                    $s_inv = $inv;
                                }
                            }
                            ?> <form method="post" id="meds_edit" class="row mb-3">
                                <input type="hidden" name="meds_edit_id" value="<?php echo $s_inv["id"]; ?>" required>
                                <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Name</span>
                                        <input value="<?php echo $s_inv["name"]; ?>" type="text" class="form-control" name="meds_edit_name" required>
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-xl-2 col-xxl-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Unit</span>
                                        <input value="<?php echo $s_inv["unit"]; ?>" type="text" class="form-control" name="meds_edit_unit" required>
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Need</span>
                                        <input step=0.01 value="<?php echo $s_inv["need"]; ?>" type="number" class="form-control" name="meds_edit_need" required min="0">
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Have</span>
                                        <input step=0.01 value="<?php echo $s_inv["have"]; ?>" type="number" class="form-control" name="meds_edit_have" required min="0">
                                    </div>
                                </div>
                                <div class="mb-2 col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Daily</span>
                                        <input step=0.01 value="<?php echo $s_inv["daily"]; ?>" type="number" class="form-control" name="meds_edit_daily" min="0" required>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 col-xl-2 col-xxl-3">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-arrow-repeat"></i> Update</button>
                                </div>
                            </form>
                        <?php } ?>
                        <table class="table table-striped" id="meds">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Objekt</th>
                                    <th>Sollte</th>
                                    <th>Ist</th>
                                    <th>Daily</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($meds as $lol){
                                    echo"<tr class='text-break'>";
                                        echo"<td>".$lol["id"]."</td>";
                                        echo"<td>".$lol["name"]."</td>";
                                        echo"<td><b>".$lol["need"]."</b> ".$lol["unit"]."</td>";
                                        echo"<td><b>".$lol["have"]."</b> ".$lol["unit"]."</td>";
                                        if($lol["daily"] > 0){
                                            echo"<td>".$lol["daily"]."</td>";
                                        } else{
                                            echo"<td>-</td>";
                                        }
                                        if($lol["have"] < $lol["need"]){
                                            echo"<td><p class='text-danger m-0'>Ersetzen</p></td>";
                                        } else{
                                            echo"<td><p class='text-success m-0'>Okay</p></td>";
                                        }
                                    echo"</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="nav-4ve23" role="tabpanel" aria-labelledby="nav-4ve23-tab" tabindex="0">
                        <h1 class="display-4">4-VE2-3</h1>
                        <p class="text-black-50">Calculate lots of data relevant to E2-manipulation with EV with E2 levels pre-manipulation, 4 days and certain number of days after injection</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div>
                            <form class="row" method="POST" action="javascript:void(0);" id="e2form">
                                <div class="col-6 col-md-3 col-sm-6 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <label class="form-label">Injection dosage</label>
                                        <input id="dosagemg" type="number" class="form-control" step=any min="0" max="10" placeholder="mg EV" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-4 col-sm-6 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <label class="form-label">Wanted E2 level</label>
                                        <input id="wantede2" type="number" class="form-control" step=any placeholder="ng/ml (median)" required>
                                    </div>    
                                </div>
                                <div class="col-12 col-md-5 col-sm-6 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <label class="form-label">E2 after <select class="form-select d-inline-block" required style="width:70px;" id="days"><option value="8">8</option><option value="12">12</option><option value="16">16</option><option value="20">20</option><option value="24">24</option><option value="28">28</option></select> days</label>
                                        <input id="e2later" type="number" class="form-control" step=any placeholder="ng/ml" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-3 col-sm-6 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <label class="form-label">Base E2 level</label>
                                        <input id="basee2" type="number" class="form-control" step=any min="0" placeholder="ng/ml" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-4 col-sm-6 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <label class="form-label">E2 level after 4 days</label>
                                        <input id="e2after4days" type="number" class="form-control" step=any placeholder="ng/ml" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-3 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <label class="form-label">Weight</label>
                                        <input id="weight2" type="number" class="form-control" step=any placeholder="kg">
                                    </div>    
                                </div>
                                <div class="col-6 col-sm-12 col-md-2 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-calculator-fill"></i> Process</button>
                                    </div>    
                                </div>
                            </form>
                        </div>
                        <div id="output" class="mt-3"></div>
                    </div>
                    <div class="tab-pane fade" id="nav-4ve21" role="tabpanel" aria-labelledby="nav-4ve21-tab" tabindex="0">
                        <h1 class="display-4">4-VE2-1</h1>
                        <p class="text-black-50">Calculate approximate needed injection dosage of EV to achieve wanted E2 level with E2 level pre-manipulation</p>
                        <div class="rounded bg-dark p-1 my-3"></div>
                        <div>
                            <form class="row" method="POST" action="javascript:void(0);" id="e3form">
                                <div class="col-6 col-md-5 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <label class="form-label">Wanted E2 level</label>
                                        <input id="wantede3" type="number" class="form-control" step=any placeholder="ng/ml (median)" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-7 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <label class="form-label">Base E2 level</label>
                                        <input id="basee3" type="number" class="form-control" step=any min="0" placeholder="ng/ml" required>
                                    </div>    
                                </div>
                                <div class="col-6 col-md-5 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <label class="form-label">Weight</label>
                                        <input id="weight3" type="number" class="form-control" step=any placeholder="kg">
                                    </div>    
                                </div>
                                <div class="col-6 col-md-5 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <label class="form-label">Tolerance</label>
                                        <input id="tolerance3" type="number" step=any class="form-control">
                                    </div>    
                                </div>
                                <div class="col-12 col-md-2 mb-3 d-flex align-items-end">
                                    <div class="w-100">    
                                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-calculator-fill"></i> Process</button>
                                    </div>    
                                </div>
                            </form>
                        </div>
                        <div id="output2" class="mt-3"></div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        
        <script src="/js/datatables.min.js"></script>

        <script>
            var eo = [
                {
                    day : 4,
                    offset : 45.3,
                    peak : 23.1
                }, {
                    day : 8,
                    offset : 63.6,
                    peak : 18.1
                }, {
                    day : 12,
                    offset : 70.8,
                    peak : 29.6
                }, {
                    day : 16,
                    offset : 73.6,
                    peak : 30.1
                }, {
                    day : 20,
                    offset : 74.4,
                    peak : 30.6
                }, {
                    day : 24,
                    offset : 74.9,
                    peak : 30.6
                }, {
                    day : 28,
                    offset : 75,
                    peak : 30.6
                }
            ];
            $(document).ready( function () {
                if(localStorage.getItem('activeTabTools') === null || localStorage.getItem('activeTabTools') === "undefined"){
                    localStorage.setItem('activeTabTools', $('div#nav-tab button').first().data('bs-target'));
                }
                $('div#nav-tab button').click(function(){
                    localStorage.setItem('activeTabTools', $(this).data('bs-target'));
                });
                $('#nav-tabContent ' + localStorage.getItem('activeTabTools')).addClass('show active');
                $('#nav-tab ' + localStorage.getItem('activeTabTools') + '-tab').addClass('active');

                $('#inv_create').hide();
                $('#counter_create').hide();
                $('#meds_create').hide();

                function CreateInventoryTable(){
                    $('#inventory').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[5, 'asc']],
                        "pageLength": 50,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '+1',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='plus' value='xd'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-calendar-check" title="Daily"></i>',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='daily' value='xd'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-123" title="Change number in inventory"></i>',
                                action: function () {
                                    let number = Number(prompt("Enter new value"));
                                    let s = "<form class='d-none' method='POST'><input type='number' name='newvalue' value='"+number+"'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '-1',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='minus' value='xd'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<div><i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    $('#inv_create').show();
                                    $('#inv_edit').hide();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='inv_edit' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this object?")){
                                        let s = "<form class='d-none' method='POST'><input type='text' name='inv_delete' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                        let htmlObject = document.createElement('div');
                                        htmlObject.innerHTML = s;
                                        let newi = htmlObject.firstChild;
                                        document.body.appendChild(newi);
                                        newi.submit();
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>Last daily on <?php echo DateTime::createFromFormat('Y-m-d H:i', $daily)->format('j/n/y H:i'); ?><br>";
                        }
                    });
                }

                function CreateMedsTable(){
                    $('#meds').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        order: [[4, 'desc'], [5, 'asc'], [2, 'desc']],
                        "pageLength": 50,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '+1',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='plusmeds' value='xd'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-calendar-check" title="Daily"></i>',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='dailymeds' value='xd'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-123" title="Change number in medicine inventory"></i>',
                                action: function () {
                                    let number = Number(prompt("Enter new value"));
                                    let s = "<form class='d-none' method='POST'><input type='number' name='newvaluemeds' value='"+number+"'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '-1',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='minusmeds' value='xd'><input name='item' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<div><i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    $('#meds_create').show();
                                    $('#meds_edit').hide();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='meds_edit' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this object?")){
                                        let s = "<form class='d-none' method='POST'><input type='text' name='meds_delete' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                        let htmlObject = document.createElement('div');
                                        htmlObject.innerHTML = s;
                                        let newi = htmlObject.firstChild;
                                        document.body.appendChild(newi);
                                        newi.submit();
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>Last daily on <?php echo DateTime::createFromFormat('Y-m-d H:i', $daily)->format('j/n/y H:i'); ?><br>";
                        }
                    });
                }

                function CreateDaycounterTable(){
                    $('#dailycounters').DataTable({
                        "pagingType": "numbers",
                        "autoWidth": false,
                        responsive: true,
                        "pageLength": 50,
                        select: {
                            style: 'single'
                        },
                        dom: 'Blfrtip',
                        buttons: [
                            {
                                text: '<i class="bi bi-arrow-clockwise"></i> Reset timer',
                                action: function () {
                                    var s = "<form class='d-none' method='POST'><input type='text' name='counterreset' value='Zurücksetzen'><input type='hidden' name='counteritem' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    var htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    var newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-dark'
                            },
                            {
                                text: '<i class="bi bi-plus-circle" title="New"></i> New',
                                action: function () {
                                    $('#counter_create').show();
                                    $('#counter_edit').hide();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-pencil-square" title="Edit"></i> Edit',
                                action: function () {
                                    let s = "<form class='d-none' method='POST'><input type='text' name='counter_edit' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                    let htmlObject = document.createElement('div');
                                    htmlObject.innerHTML = s;
                                    let newi = htmlObject.firstChild;
                                    document.body.appendChild(newi);
                                    newi.submit();
                                },
                                className: 'btn-orig btn-sm'
                            },
                            {
                                text: '<i class="bi bi-trash3-fill" title="Delete"></i> Delete',
                                action: function () {
                                    if(confirm("Are you sure that you want to delete this object?")){
                                        let s = "<form class='d-none' method='POST'><input type='text' name='counter_delete' value='"+this.rows({selected: true}).data()[0][0]+"'></form>"
                                        let htmlObject = document.createElement('div');
                                        htmlObject.innerHTML = s;
                                        let newi = htmlObject.firstChild;
                                        document.body.appendChild(newi);
                                        newi.submit();
                                    }
                                },
                                className: 'btn-danger btn-sm'
                            }
                        ],
                        "infoCallback": function( settings, start, end, max, total, pre ) {
                            return "Showing "+start+" to "+end+" of "+total+" entries<br>";
                        }
                    });
                }

                const tabs = [
                    {
                        id: 'nav-daycounter-tab',
                        isActive: false,
                        createFunction: CreateDaycounterTable
                    },
                    {
                        id: 'nav-vorrat-tab',
                        isActive: false,
                        createFunction: CreateInventoryTable
                    },
                    {
                        id: 'nav-meds-tab',
                        isActive: false,
                        createFunction: CreateMedsTable
                    }
                ];

                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        const target = mutation.target;
                        if ($(target).hasClass('active')) {
                            const tab = tabs.find(t => t.id === target.id);
                            if (tab && !tab.isActive) {
                                tab.createFunction();
                                tab.isActive = true;
                            }
                        }
                    });
                });

                tabs.forEach(tab => {
                    const element = document.querySelector(`#${tab.id}`);
                    if (element) {
                        // Check initial state on page load
                        if ($(element).hasClass('active') && !tab.isActive) {
                            tab.createFunction();
                            tab.isActive = true;
                        }
                        // Start observing for future changes
                        observer.observe(element, { attributes: true });
                    } else {
                        console.warn(`Element #${tab.id} not found`);
                    }
                });

                $('.dt-buttons button').each(function(){
                    if(!$(this).hasClass('btn-orig')){
                        $(this).removeClass('btn-secondary');
                    }
                });

                $('#e2form').change(function() {
                    $('#wantede2').attr({
                        "min" : Number($('#basee2').val())
                    });
                    $('#e2after4days').attr({
                        "min" : Number($('#basee2').val())
                    });
                    $('#e2later').attr({
                        "min" : Number($('#e2after4days').val())
                    });
                });
                $('#e2form').on("submit", function() {
                    let mg = Number($('#dosagemg').val());
                    let base = Number($('#basee2').val());
                    let e2after4days = Number($('#e2after4days').val());
                    let days = Number($('#days').val());
                    let e2later = Number($('#e2later').val());
                    let wantede2 = Number($('#wantede2').val());
                    let weight = Number($('#weight2').val());

                    let averageobjdays = eo.find(n=>n.day==days);
                    let averageobj4 = eo[0];
                    let averageobj28 = eo[6];

                    let average4higher = (averageobj4.offset)*1/mg;
                    let averagedayshigher = (averageobjdays.offset)*1/mg;
                    let ade2after4days = (e2after4days-base) * 1/mg;
                    let adje2later = (e2later-base) * 1/mg;

                    let difference4 = ade2after4days/average4higher;
                    let differencedays = adje2later/averagedayshigher;

                    let changeindifference = (difference4/differencedays)-1;
                    let newbaseline = base-(e2later*changeindifference);
                    let baselinechange = ((base/newbaseline)-1)*100;
                    
                    let e2latermedian = ((e2later+((e2later*(100+averageobjdays.peak))/100))/2);
                    let e2laterpeak = ((e2later*(100+averageobjdays.peak))/100);
                    let e2after4daysmedian = ((e2after4days+((e2after4days*(100+averageobj4.peak))/100))/2);
                    let e2after4dayspeak = ((e2after4days*(100+averageobj4.peak))/100);

                    let daychange = averageobj28.offset/averageobjdays.offset;
                    let continuationstop = (e2latermedian-base)*daychange;
                    let continuationreduction = (e2latermedian-base*((base/newbaseline)-1))*daychange;
                    let continuation = e2latermedian*daychange;

                    let wantedstop = mg * (wantede2/continuationstop);
                    let wantedreduction = mg * ((wantede2-newbaseline)/(continuationreduction-newbaseline));
                    let wanted = mg * ((wantede2-base)/(continuation-base));

                    let tolerancestop = continuationstop/(((averageobj28.offset*(100+averageobj28.peak))/100)/2*mg)/weight*10000;
                    let tolerancereduction = continuationreduction/(((averageobj28.offset*(100+averageobj28.peak))/100)/2*mg)/weight*10000;
                    let tolerance = continuation/(((averageobj28.offset*(100+averageobj28.peak))/100)/2*mg)/weight*10000;
                    
                    $('#output').empty();
                    $('#output').append("<span class='d-block mb-2'>E2 after 4 days:</span>");
                    $('#output').append("<ul><li><mark>"+e2after4days.toFixed(2)+" ng/ml</mark></li><li>Estimated peak: <mark>"+e2after4dayspeak.toFixed(2)+" ng/ml</mark></li><li>Estimated median: <mark>"+e2after4daysmedian.toFixed(2)+" ng/ml</mark></li></ul>");
                    $('#output').append("<span class='d-block mb-2'>E2 after <mark>"+days+" days</mark>:</span>");
                    $('#output').append("<ul><li><mark>"+e2later.toFixed(2)+" ng/ml</mark></li><li>Estimated peak: <mark>"+e2laterpeak.toFixed(2)+" ng/ml</mark></li><li>Estimated median: <mark>"+e2latermedian.toFixed(2)+" ng/ml</mark></li></ul>");
                    if(baselinechange>0){
                        $('#output').append("<span class='d-block mb-2'>Estimated reduction of natural E2 production: <mark>"+baselinechange.toFixed(2)+"%</mark>, from <mark>"+base+" ng/ml</mark> to <mark>"+newbaseline.toFixed(2)+" ng/ml</mark> in <mark>"+days+" days</mark></span>");
                    }
                    $('#output').append("<span class='d-block mb-2'>Continuation of regimen will lead to estimated median E2 levels of:</span>");
                    if(baselinechange>0){
                        $('#output').append("<ul><li><mark>"+continuationstop.toFixed(2)+" ng/ml</mark> with stop of natural E2 production</li><li><mark>"+continuationreduction.toFixed(2)+" ng/ml</mark> with reduction of natural E2 production of <mark>"+baselinechange.toFixed(2)+"%</mark></li><li><mark>"+continuation.toFixed(2)+" ng/ml</mark> with no change in natural E2 production</li></ul>");
                    } else {
                        $('#output').append("<ul><li><mark>"+continuationstop.toFixed(2)+" ng/ml</mark> with stop of natural E2 production</li><li><mark>"+continuation.toFixed(2)+" ng/ml</mark> with no change in natural E2 production</li></ul>");
                    }
                    $('#output').append("<span class='d-block mb-2'>To get to E2 levels of <mark>"+wantede2.toFixed(2)+" ng/ml</mark>:</span>");
                    if(baselinechange>0){
                        $('#output').append("<ul><li><mark>"+wantedstop.toFixed(2)+" mg EV / 4 days</mark> with stop of natural E2 production</li><li><mark>"+wantedreduction.toFixed(2)+" mg EV / 4 days</mark> with reduction of natural E2 production of <mark>"+baselinechange.toFixed(2)+"%</mark></li><li><mark>"+wanted.toFixed(2)+" mg EV / 4 days</mark> with no change in natural E2 production</li>");
                    } else {
                        $('#output').append("<ul><li><mark>"+wantedstop.toFixed(2)+" mg EV / 4 days</mark> with stop of natural E2 production</li><li><mark>"+wanted.toFixed(2)+" mg EV / 4 days</mark> with no change in natural E2 production</li>");
                    }
                    $('#output').append("<span class='d-block mb-2'>Tolerance:</span>");
                    if(baselinechange>0){
                        $('#output').append("<ul><li><mark>"+tolerancestop.toFixed()+"</mark> with stop of natural E2 production</li><li><mark>"+tolerancereduction.toFixed()+"</mark> with reduction of natural E2 production of <mark>"+baselinechange.toFixed(2)+"%</mark></li><li><mark>"+tolerance.toFixed()+"</mark> with no change in natural E2 production</li>");
                    } else {
                        $('#output').append("<ul><li><mark>"+tolerancestop.toFixed()+"</mark> with stop of natural E2 production</li><li><mark>"+tolerance.toFixed()+"</mark> with no change in natural E2 production</li>");
                    }
                });
                $('#e3form').change(function() {
                    $('#wantede3').attr({
                        "min" : Number($('#basee3').val())
                    });
                });
                $('#e3form').on("submit", function() {
                    let base = Number($('#basee3').val());
                    let wantede3 = Number($('#wantede3').val());
                    let weight = Number($('#weight3').val());
                    let tolerance = Number($('#tolerance3').val());

                    let averageobj28 = eo[6];

                    let adjwanted = (wantede3-base);
                    let objmedian = (averageobj28.offset+((averageobj28.offset*(100+averageobj28.peak))/100))/2;

                    let stop = wantede3/objmedian*weight*tolerance/10000;
                    let nochange = adjwanted/objmedian*weight*tolerance/10000;

                    $('#output2').empty();
                    $('#output2').append("<span class='d-block mb-2'>To get to E2 levels of <mark>"+wantede3.toFixed(2)+" ng/ml</mark>:</span>");
                    $('#output2').append("<ul><li><mark>"+stop.toFixed(2)+" mg EV / 4 days</mark> with stop of natural E2 production</li><li><mark>"+nochange.toFixed(2)+" mg EV / 4 days</mark> with no change in natural E2 production</li>");
                });
            } );
        </script>
    </body>
</html>
