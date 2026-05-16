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
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $nav = file_get_contents('../nav');
            $tasks = json_decode(file_get_contents('../../json/lists.json'), true)['tasks'];

            function Authenticate(){
                global $users;
                foreach($users as $lola){
                    if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                        return true;
                    }
                }
                return false;
            }

            eval("?> $nav <?php ");

            if(Authenticate()){
                if(isset($_POST["newtask"]) && isset($_POST["newtasktype"]) && !empty($_POST["newtask"]) && $_POST["newtask"] != "null"){
                    foreach($tasks as &$task){
                        if($task['type'] == $_POST["newtasktype"]){
                            array_push($task['tasks'], (array) ['name' => $_POST["newtask"], 'completed' => false]);
                        }
                    }
                    unset($task);
                    file_put_contents('../../json/lists.json', json_encode(array("tasks" => $tasks), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["deltasktype"]) && isset($_POST["deltask"])){
                    foreach($tasks as &$task){
                        if($task['type'] == $_POST["deltasktype"]){
                            foreach($task['tasks'] as $key => $value){
                                if($value["name"] == $_POST["deltask"]){
                                    unset($task['tasks'][$key]);
                                }
                            }
                        }
                    }
                    unset($task);
                    file_put_contents('../../json/lists.json', json_encode(array("tasks" => $tasks), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["deletelist"])){
                    foreach($tasks as $key => $value){
                        if($value["type"] == $_POST["deletelist"]){
                            unset($tasks[$key]);
                        }
                    }
                    file_put_contents('../../json/lists.json', json_encode(array("tasks" => $tasks), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
                if(isset($_POST["shortcut"]) && isset($_POST["title"]) && isset($_POST["desc"]) && isset($_POST["checklist"])){
                    $newtask["type"] = $_POST["shortcut"];
                    $newtask["title"] = $_POST["title"];
                    $newtask["desc"] = $_POST["desc"];
                    $_POST["checklist"] == "true" ? $newtask["checklist"] = true : $newtask["checklist"] = false;
                    $newtask["tasks"] = array();

                    array_push($tasks, $newtask);
                    file_put_contents('../../json/lists.json', json_encode(array("tasks" => $tasks), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }
        ?>
        <div class="container my-3">
            <?php
                if(!Authenticate()){
                    echo"You need to be logged in to access this page.";
                }else{
                ?>
                    <nav class="mb-3">
                        <div class="nav nav-pills" id="nav-tab" role="tablist">
                            <?php
                                foreach($tasks as $task){
                                    echo'<button class="nav-link" id="nav-'.$task["type"].'-tab" data-bs-toggle="tab" data-bs-target="#nav-'.$task["type"].'" type="button" role="tab" aria-controls="nav-'.$task["type"].'" aria-selected="false">'.$task["title"].'</button>';
                                }
                            ?>
                            <button class="nav-link" id="nav-createtasklist-tab" data-bs-toggle="tab" data-bs-target="#nav-createtasklist" type="button" role="tab" aria-controls="nav-createtasklist" aria-selected="false"><i class="bi bi-plus-circle" title="New"></i> New</button>
                        </div>
                    </nav>
                    <div class="tab-content" id="nav-tabContent">
                        <?php
                            foreach($tasks as $key => $task){
                                echo'<div class="tab-pane fade" id="nav-'.$task["type"].'" role="tabpanel" aria-labelledby="nav-'.$task["type"].'-tab" tabindex="0">';
                                ?>
                                        <div class="taskcol">
                                            <div class="d-flex align-items-center">
                                                <h1 class="display-4 d-inline-block"><?php echo $task["title"]; ?></h1>
                                                <button class="addtaskbtn btn btn-success btn-sm ms-3 rounded-lg" id="<?php echo $task["type"]; ?>"><i class="bi bi-plus-lg"></i> Add</button>
                                                <?php if($task["checklist"]){ ?>
                                                    <button class="resettaskbtn btn btn-secondary btn-sm ms-1 rounded-lg" id="<?php echo $task["type"]; ?>"><i class="bi bi-repeat"></i> Reset</button>
                                                <?php } ?>
                                                <button data-type="<?php echo $task["type"]; ?>" class="deletelistbtn btn btn-danger btn-sm ms-1 rounded-lg" id="<?php echo $task["type"]; ?>"><i class="bi bi-trash3-fill"></i> Delete</button>
                                            </div>
                                            <p class="text-black-50"><?php echo $task["desc"]; ?></p>
                                            <div class="rounded bg-dark p-1 my-3"></div>
                                            <table class="table-striped table">
                                                <thead>
                                                <tr>
                                                    <th>Task</th>
                                                    <?php
                                                        if($task["checklist"]){
                                                            echo "<th>Done</th>";
                                                        }
                                                    ?>
                                                    <th class="text-end">Remove</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                    foreach($task["tasks"] as $key2 => $member){
                                                        echo '<tr id="' . $key . "_" . $key2 . '">';
                                                        echo '<td>' . $member["name"] . '</td>';
                                                        if($task["checklist"]){
                                                            echo '<td><input class="form-check-input" type="checkbox"></td>';
                                                        }
                                                        echo '<td class="text-end"><button class="deltaskbtn btn btn-danger btn-sm" data-type="'. $task["type"] .'" data-task="'. $member["name"] .'"><i class="bi bi-trash3-fill"></i> Delete</button></td>';
                                                        echo '</tr>';
                                                    }
                                                ?>
                                                </tbody>
                                            </table>
                                            <?php if($task["checklist"]){ ?>
                                                <div class="progress"></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>
                        <div class="tab-pane fade" id="nav-createtasklist" role="tabpanel" aria-labelledby="nav-createtasklist-tab" tabindex="0">
                            <form method='POST'>
                                <div class="mb-3">
                                    <label class="form-label mb-1">Shortcut</label>
                                    <input required name="shortcut" class="form-control form-control-sm" type="text" placeholder="daily">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label mb-1">Title</label>
                                    <input required name="title" class="form-control form-control-sm" type="text" placeholder="Daily Tasks">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label mb-1">Description</label>
                                    <input required name="desc" class="form-control form-control-sm" type="text" placeholder="Do this every day">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label mb-1">List type</label>
                                    <select required class="form-select form-select-sm" name="checklist">
                                        <option value="true">Checklist</option>
                                        <option value="false">Normal list</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="bi bi-cloud-arrow-up"></i> Create</button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/datatables.min.js"></script>
        <?php if(Authenticate()){ ?>
            <script>
                $(document).ready(function() {
                    if(localStorage.getItem('activeTabTasks') === null || localStorage.getItem('activeTabTasks') === "undefined"){
                        localStorage.setItem('activeTabTasks', $('div#nav-tab button').first().data('bs-target'));
                    }
                    $('div#nav-tab button').click(function(){
                        localStorage.setItem('activeTabTasks', $(this).data('bs-target'));
                    });
                    $('#nav-tabContent ' + localStorage.getItem('activeTabTasks')).addClass('show active');
                    $('#nav-tab ' + localStorage.getItem('activeTabTasks') + '-tab').addClass('active');
                    
                    $('input[type="checkbox"]').each(function(){
                        if(localStorage.getItem($(this).parent().parent().attr("id")) == "active"){
                            $(this).prop("checked", true);
                            $(this).parent().parent().addClass('table-success');
                        }
                    });
                    $('input[type="checkbox"]').click(function() {
                        if($(this).prop("checked") == true){
                            $(this).parent().parent().addClass('table-success');
                            localStorage.setItem($(this).parent().parent().attr("id"), 'active');
                        }
                        else if($(this).prop("checked") == false){
                            $(this).parent().parent().removeClass('table-success');
                            localStorage.removeItem($(this).parent().parent().attr("id"));
                        }
                    });
                    function UpdateProgress(){
                        $('.taskcol').each(function(){
                            let total = 0;
                            let done = 0;
                            $(this).find('input[type="checkbox"]').each(function(){
                                total++;
                                if($(this).prop("checked") == true){
                                    done++;
                                }
                            });
                            var progress = (done / total) * 100;
                            $(this).find('.progress').html('<div class="progress-bar progress-bar-striped" role="progressbar" style="width: ' + progress + '%" aria-valuenow="' + progress + '" aria-valuemin="0" aria-valuemax="100"></div>');
                        });
                    }
                    UpdateProgress();
                    $('input[type="checkbox"]').click(function() {
                        UpdateProgress();
                    });
                    $('.addtaskbtn').click(function(){
                        let task = prompt("Enter task name");
                        let type = $(this).attr('id');
                        let s = "<form class='d-none' method='POST'><input type='text' name='newtasktype' value='"+type+"'><input type='text' name='newtask' value='"+task+"'></form>"
                        let htmlObject = document.createElement('div');
                        htmlObject.innerHTML = s;
                        let newi = htmlObject.firstChild;
                        document.body.appendChild(newi);
                        newi.submit();
                    });
                    $('.deltaskbtn').click(function(){
                        let data = {
                            "type": $(this).attr('data-type'),
                            "task": $(this).attr('data-task')
                        };
                        let s = "<form class='d-none' method='POST'><input type='text' name='deltasktype' value='"+data.type+"'><input type='text' name='deltask' value='"+data.task+"'></form>"
                        let htmlObject = document.createElement('div');
                        htmlObject.innerHTML = s;
                        let newi = htmlObject.firstChild;
                        document.body.appendChild(newi);
                        newi.submit();
                    });
                    $('table').each(function(){
                        $(this).DataTable({
                            "autoWidth": false,
                            responsive: true,
                            "pageLength": 100,
                            dom: 'rt'
                        });
                    })
                    $('.resettaskbtn').click(function(){
                        $(this).parent().parent().find('input[type="checkbox"]').each(function(){
                            $(this).prop("checked", false);
                            $(this).parent().parent().removeClass('table-success');
                            localStorage.removeItem($(this).parent().parent().attr("id"));
                            UpdateProgress();
                        });
                    });
                    $('.deletelistbtn').click(function(){
                        if(confirm("Are you sure you want to delete this list?")){
                            let data = {
                                "type": $(this).attr('data-type')
                            };
                            let s = "<form class='d-none' method='POST'><input type='text' name='deletelist' value='"+data.type+"'></form>"
                            let htmlObject = document.createElement('div');
                            htmlObject.innerHTML = s;
                            let newi = htmlObject.firstChild;
                            document.body.appendChild(newi);
                            newi.submit();
                        }
                    });
                });
            </script>
        <?php } ?>
    </body>
</html>
