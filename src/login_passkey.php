<?php
// if you want just put your key cuz it's one person auth.
// $storedString = "YOUR_KEY_HERE";
// $credential = unserialize(base64_decode($storedString));

// Default: read from file .dat
$credential = unserialize(
    file_get_contents(
        __DIR__ . '/credential.dat'
    )
);


require __DIR__ . '/vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

session_start();

$webauthn = new WebAuthn(
    'My Solo App',
    $_SERVER['HTTP_HOST']
);

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

    $credentialIds = [
        $credential->credentialId
    ];

    $args = $webauthn->getGetArgs(
        $credentialIds
    );

    $_SESSION['login_challenge'] =
        serialize(
            $webauthn->getChallenge()
        );

    $args = byteBufferToArray(
        $args
    );

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
<title>Passkey Login</title>
</head>
<body>

<h2>Passkey Login</h2>

<button id="loginBtn">
Login
</button>

<script>

const args = <?php echo $jsonArgs; ?>;

args.publicKey.challenge =
    new Uint8Array(
        args.publicKey.challenge
    );

if (
    args.publicKey.allowCredentials
) {

    args.publicKey.allowCredentials
    .forEach(c => {

        c.id =
            new Uint8Array(
                c.id
            );

    });
}

function bufferToBase64(buffer)
{
    let binary = '';

    const bytes =
        new Uint8Array(buffer);

    for (
        let i = 0;
        i < bytes.length;
        i++
    ) {
        binary +=
            String.fromCharCode(
                bytes[i]
            );
    }

    return btoa(binary);
}

document
.getElementById('loginBtn')
.addEventListener(
    'click',
    async () => {

        try {

            const assertion =
                await navigator
                .credentials
                .get({
                    publicKey:
                        args.publicKey
                });

            const payload = {

                id:
                    assertion.id,

                rawId:
                    bufferToBase64(
                        assertion.rawId
                    ),

                type:
                    assertion.type,

                response: {

                    clientDataJSON:
                        bufferToBase64(
                            assertion.response.clientDataJSON
                        ),

                    authenticatorData:
                        bufferToBase64(
                            assertion.response.authenticatorData
                        ),

                    signature:
                        bufferToBase64(
                            assertion.response.signature
                        )
                }
            };

            const form =
                document.createElement(
                    'form'
                );

            form.method = 'POST';

            const input =
                document.createElement(
                    'input'
                );

            input.type = 'hidden';
            input.name = 'response';

            input.value =
                JSON.stringify(
                    payload
                );

            form.appendChild(
                input
            );

            document.body.appendChild(
                form
            );

            form.submit();

        } catch (e) {

            alert(
                'Login failed:\n\n'
                + e.message
            );

        }

    });

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
            $_SESSION['login_challenge']
        );

    $result =
        $webauthn->processGet(

            base64_decode(
                $response->response->clientDataJSON
            ),

            base64_decode(
                $response->response->authenticatorData
            ),

            base64_decode(
                $response->response->signature
            ),

            $credential->credentialPublicKey,

            $challenge,

            $credential->signatureCounter
        );

    $_SESSION['logged_in'] = true;

    echo '<h1>LOGIN SUCCESS</h1>';

} catch (Exception $e) {

    echo '<h1>LOGIN FAILED</h1>';

    echo '<pre>';

    echo htmlspecialchars(
        $e->getMessage()
    );

    echo '</pre>';
}
