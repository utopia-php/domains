curl -X 'PUT' \
  'https://api.ote-godaddy.com/v1/domains/bestqnypcb.net' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: sso-key 3mM44UcgmoAr2B_8SVYSdidUErkNsgbdB4mHM:ApFVR8GYNSw2f7E9CqbjQa' \
  -d '{
    "nameServers": ["ns1.example.com","ns2.example.com"],
    "consent": {
      "agreedAt": "2023-02-07T15:12:16.000Z",
      "agreedBy": "127.0.0.1",
      "agreementKeys": [
        "EXPOSE_WHOIS"
      ]
    },
    "exposeWhois": true,
    "locked": true,
    "renewAuto": true
  }
}'