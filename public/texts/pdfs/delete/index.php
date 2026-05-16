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
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body>
        <?php
            $pdfs = json_decode(file_get_contents('../../../../json/pdfs.json'), true)['pdfs'];
            $users = json_decode(file_get_contents('../../../../json/users.json'), true)['users'];
            $nav = file_get_contents('../../../nav');

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
        ?>
        <div class="container my-3">
            <?php
                if(!Authenticate()){
                    echo "You need to be logged in to access this page.";
                } else{ 
                    foreach ($pdfs as $key => $field) {
                        if ($field['id'] == $_GET["id"]) {
                            unlink("../../../../pdf/pdfs/" . $field["filename"]);
                            unset($pdfs[$key]);
                        }
                    }

                    $gringo = array('pdfs'=>$pdfs);
                    file_put_contents('../../../../json/pdfs.json', json_encode($gringo, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                    
                    header("Location: /texts/");
                    die();
                }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
    </body>
</html>