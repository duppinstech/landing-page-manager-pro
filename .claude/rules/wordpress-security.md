# WordPress Security

- Check capabilities before privileged admin or REST operations; do not rely on a nonce as authorization.
- Verify nonces for state-changing requests and follow established WordPress REST permission callbacks.
- Validate and sanitize untrusted input with APIs appropriate to its type. Escape values at output using the correct context-specific function.
- Use prepared database queries when adding direct SQL; avoid exposing private analytics to unauthorized users.
- Do not log credentials, tokens, personal data, or raw request payloads.
- For security-sensitive changes, consider unauthenticated, unauthorized, malformed, and boundary inputs.