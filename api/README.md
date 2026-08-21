# Vodafone Inbound Call API - Core PHP

## Endpoints

POST /api/on-call-connected
POST /api/on-call-fail
POST /api/post-call

Content-Type: application/json

## 1. On Call Connected

Request:

{
    "Callid": "123456789",
    "Dni": "82811651477",
    "Cli": "8657929461",
    "Agent": "7892722672",
    "Status": "onCallConnect"
}

## 2. On Call Fail

Request:

{
    "Callid": "123456789",
    "Dni": "82811651477",
    "Cli": "8657929461",
    "Agent": "7892722672",
    "Status": "onCallFail"
}

## 3. Post Call

Request:

{
    "Callid": "123456789",
    "Dni": "82811651477",
    "Cli": "8657929461",
    "Agent": "7892722672",
    "Call_Start_Time": "2026-08-11 10:30:00",
    "Call_End_Time": "2026-08-11 10:35:25",
    "Call_Duration": 325,
    "OG_Start_Time": "2026-08-11 10:30:05",
    "OG_Duration": 320,
    "OG_End_Time": "2026-08-11 10:35:25",
    "Recording_URL": "https://example.com/recordings/123456789.wav"
}

## Deployment

1. Upload this folder to your web server.
2. Configure your domain/virtual host.
3. Set API_KEY in config.php.
4. Enable validateApiKey() in all three endpoint files if authentication is required.
5. Ensure the logs directory is writable by PHP.
6. Enable HTTPS.
7. Test using Postman or cURL.

## Example URLs

https://yourdomain.com/api/on-call-connected
https://yourdomain.com/api/on-call-fail
https://yourdomain.com/api/post-call

Replace yourdomain.com with the actual production domain.
