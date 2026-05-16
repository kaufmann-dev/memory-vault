<?php
    session_start();
?>
<!DOCTYPE html>
<html class="h-100" lang="en">
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
    <body class="h-100">
        <?php
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
        ?>
        <div class="h-100 d-flex flex-column">
            <div class="flex-soy">
                <?php eval("?> $nav <?php "); ?>
            </div>
            <div class="flex-chad">
                <div class="container my-3">
                    <h1 class="display-4">Privacy Statement</h1>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <p>This website only uses essential cookies to enhance security, improve performance and ensure its functionality. These cookies are necessary for the proper operation of the site and do not require consent.</p><p>No analytics tools, tracking tools, or advertising tools are used on this website. Additionally, no embedded content from third-party providers is included.</p><p>This website uses Cloudflare, a content delivery network certified under the EU-U.S. Data Privacy Framework, to enhance security, improve performance, and ensure its functionality. This may involve setting essential cookies to maximize network resources, manage traffic, and protect against malicious traffic. Data such as your IP address may be transmitted to Cloudflare servers. The use of Cloudflare is in the interest of providing a secure and optimized experience for users.</p><p>You have the right to access, correct, delete, and restrict the processing of your personal data. You may also object to the processing of your data and have the right to data portability.</p><p>I reserve the right to update this privacy statement if necessary. The current version will be published on my website.</p>
                </div>
            </div>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
    </body>
</html>