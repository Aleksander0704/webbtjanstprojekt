# Backend API - User Guide

Denna guide är för användare som vill logga in och verifiera sin sessionsnyckel.

## 🔐 Autentisering

### 1. Logga in - POST /login

För att komma åt systemet måste du först logga in med ditt användarnamn och lösenord.

**Endpoint:** `POST http://host/login`

**Vad du behöver skicka:**
```json
{
  "username": "din_användare",
  "password": "ditt_lösenord"
}
```

**Exempel med cURL:**
```bash
curl -X POST http://host/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser1","password":"test123"}'
```

**Exempel med JavaScript/Fetch:**
```javascript
fetch('http://host/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'testuser1',
    password: 'test123'
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

**Vad du får tillbaka vid lyckat login:**
```json
{
  "success": true,
  "user_id": 1,
  "session_key": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2",
  "services": 7,
  "expires_at": "2025-11-19 15:30:00"
}
```

**Spara denna information:**
- 🔑 `session_key` - Din autentiseringsnyckel (använd denna för framtida förfrågningar)
- 👤 `user_id` - Ditt användar-ID
- 📋 `services` - Vilka tjänster du har tillgång till (se nedan)
- ⏰ `expires_at` - När din nyckel förfaller

**Vad betyder services-värdet:**
- `1` = Kalender 📅
- `2` = Blogg 📝
- `4` = Wiki 📖
- `7` = Alla tjänster (1 + 2 + 4)

**Vid felaktiga uppgifter:**
```json
{
  "error": "Felaktigt användarnamn eller lösenord"
}
```

---

### 2. Verifiera din nyckel - POST /verify

För att bekräfta att din sessionsnyckel fortfarande är giltig, skicka den till verify-endpoint.

**Endpoint:** `POST http://host/verify`

**Vad du behöver skicka:**
```json
{
  "session_key": "a1b2c3d4e5f6..."
}
```

**Exempel med cURL:**
```bash
curl -X POST http://host/verify \
  -H "Content-Type: application/json" \
  -d '{"session_key":"a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2"}'
```

**Exempel med JavaScript/Fetch:**
```javascript
const sessionKey = "a1b2c3d4e5f6..."; // Din nyckel från login

fetch('http://host/verify', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ session_key: sessionKey })
})
.then(res => res.json())
.then(data => {
  if (data.valid) {
    console.log('✅ Du är inloggad! Nyckel gäller till:', data.new_expires_at);
  } else {
    console.log('❌ Din nyckel är inte giltig. Logga in igen.');
  }
});
```

**Vad du får tillbaka om nyckeln är giltig:**
```json
{
  "valid": true,
  "user_id": 1,
  "services": 7,
  "new_expires_at": "2025-11-19 15:35:00"
}
```

**Vid ogiltig eller utgångna nyckel:**
```json
{
  "valid": false
}
```

---

### 3. Logga ut - POST /logout

För att logga ut och invalidera din sessionsnyckel.

**Endpoint:** `POST http://host/logout`

**Vad du behöver skicka:**
```json
{
  "session_key": "a1b2c3d4e5f6..."
}
```

**Exempel med cURL:**
```bash
curl -X POST http://host/logout \
  -H "Content-Type: application/json" \
  -d '{"session_key":"a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2"}'
```

**Respons vid lyckat logout:**
```json
{
  "success": true,
  "message": "Utloggad"
}
```

---

## 📱 Vanliga Use Cases

### Use Case 1: Skydda en API-förfrågan

```javascript
// 1. Logga in
const loginResp = await fetch('http://host/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'myuser',
    password: 'mypass'
  })
}).then(r => r.json());

const sessionKey = loginResp.session_key;

// 2. Verififera innan varje större operation
const verifyResp = await fetch('http://host/verify', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ session_key: sessionKey })
}).then(r => r.json());

if (verifyResp.valid) {
  console.log('✅ Autentiserad!');
  // Gör dina operationer här
} else {
  console.log('❌ Session utgångna. Logga in igen.');
}

// 3. Logga ut när du är klar
await fetch('http://host/logout', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ session_key: sessionKey })
});
```

### Use Case 2: Lagra nyckeln lokalt (Web App)

```javascript
// Efter login, spara nyckeln
const loginData = await fetch('...').then(r => r.json());
localStorage.setItem('session_key', loginData.session_key);

// Senare, hämta nyckeln
const sessionKey = localStorage.getItem('session_key');

// Verifiera innan operation
const verified = await fetch('...').then(r => r.json());
if (!verified.valid) {
  // Nyckel utgångna - ta användaren tillbaka till login
  localStorage.removeItem('session_key');
  window.location.href = '/login';
}
```

---

## ⏱️ Viktigt om sessionstimeout

- 🕐 **Timeout:** Alla nycklar förfaller efter **30 minuter** av inaktivitet
- 🔄 **Auto-renew:** Varje gång du anropar `/verify` förlängs nyckeln med 30 minuter
- ⚠️ **Utgångna nycklar:** Om din nyckel utgår måste du logga in igen

**Exempel:** Om du loggar in kl 14:00, förfaller nyckeln kl 14:30. Men om du verifierar kl 14:25, förfaller den då kl 14:55.

---

## 🆘 Felsökning

### "Felaktigt användarnamn eller lösenord"
- ✓ Dubbelkolla stavningen på ditt användarnamn
- ✓ Verifiera att lösenordet är rätt (case-sensitive!)
- ✓ Kontakta admin om du glömt ditt lösenord

### "Session gäller inte längre"
- ✓ Din nyckel har förfallit (timeout efter 30 min inaktivitet)
- ✓ Du måste logga in igen med `/login`
- ✓ Om du ofta får timeout, anropa `/verify` oftare för att förnya nyckeln

### "Ogiltigt användar-ID"
- ✓ Kontrollera att du använder rätt `user_id` från login-svaret
- ✓ Du kan inte hitta detta värde själv - det får du från `/login`

---

## 🔒 Säkerhetstips

✅ **Gör detta:**
- Lagra din sessionsnyckel säkert (använd HTTPS, aldrig HTTP)
- Logga ut när du är klar (anropa `/logout`)
- Behandla din nyckel som ett lösenord

⛔ **Gör inte detta:**
- Dela din sessionsnyckel med andra
- Skicka nyckeln via okrypterad HTTP
- Exponera nyckeln i browser console logs
- Hardkoda nyckeln i din kod

