<?php

require __DIR__ . '/vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

session_start();

define("FLAG_ENABLE_PASSKEY_REG", false);
if (FLAG_ENABLE_PASSKEY_REG === false) die("PASSKEY REG NOT AVAILABLE AT THE MOMENT.");

$rpName = 'Example App';
$rpId   = $_SERVER['HTTP_HOST'];

$webauthn = new WebAuthn($rpName, $rpId);

function byteBufferToArray($obj)
{
    if ($obj instanceof ByteBuffer) {

        $ref = new ReflectionClass($obj);

        $prop = $ref->getProperty('_data');
        $prop->setAccessible(true);

        $data = $prop->getValue($obj);

        return array_values(
            unpack('C*', $data)
        );
    }

    if (is_object($obj)) {

        foreach ($obj as $k => $v) {
            $obj->$k = byteBufferToArray($v);
        }

        return $obj;
    }

    if (is_array($obj)) {

        foreach ($obj as $k => $v) {
            $obj[$k] = byteBufferToArray($v);
        }

        return $obj;
    }

    return $obj;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $userId = 'solo_admin';

    $args = $webauthn->getCreateArgs(
        $userId,
        'admin',
        'Administrator'
    );

    $_SESSION['challenge'] = serialize(
        $webauthn->getChallenge()
    );

    $args = byteBufferToArray($args);

    $jsonArgs = json_encode(
        $args,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Register Passkey</title>
</head>
<body>

<h2>Register Passkey</h2>

<button id="regBtn">
Register Device
</button>

<script>

const args = <?php echo $jsonArgs; ?>;

args.publicKey.challenge =
    new Uint8Array(
        args.publicKey.challenge
    );

args.publicKey.user.id =
    new Uint8Array(
        args.publicKey.user.id
    );

function bufferToBase64(buffer)
{
    let binary = '';

    const bytes =
        new Uint8Array(buffer);

    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }

    return btoa(binary);
}

document
.getElementById('regBtn')
.addEventListener(
    'click',
    async () => {

        try {

            const credential =
                await navigator.credentials.create({
                    publicKey: args.publicKey
                });

            const payload = {

                id: credential.id,

                type: credential.type,

                rawId: bufferToBase64(
                    credential.rawId
                ),

                response: {

                    clientDataJSON:
                        bufferToBase64(
                            credential.response.clientDataJSON
                        ),

                    attestationObject:
                        bufferToBase64(
                            credential.response.attestationObject
                        )
                }
            };

            const form =
                document.createElement('form');

            form.method = 'POST';

            const input =
                document.createElement('input');

            input.type = 'hidden';
            input.name = 'response';
            input.value =
                JSON.stringify(payload);

            form.appendChild(input);

            document.body.appendChild(form);

            form.submit();

        } catch (e) {

            console.error(e);

            alert(
                'Registration failed:\n\n' +
                e.message
            );
        }
    }
);

</script>

</body>
</html>
<?php
exit;
}

$response =
    json_decode(
        $_POST['response']
    );

try {

    $challenge =
        unserialize(
            $_SESSION['challenge']
        );

    $credentialData =
        $webauthn->processCreate(

            base64_decode(
                $response->response->clientDataJSON
            ),

            base64_decode(
                $response->response->attestationObject
            ),

            $challenge
        );

    echo "<h1>SUCCESS</h1>";

    echo "<p>Credential here:</p>";

    echo "<textarea style='width:100%;height:300px'>";

    echo htmlspecialchars(
        base64_encode(
            serialize(
                $credentialData
            )
        )
    );

    echo "</textarea>";

} catch (Exception $e) {

    echo "<h2>FAILED</h2>";

    echo "<pre>";

    echo htmlspecialchars(
        $e->getMessage()
    );

    echo "</pre>";
}
