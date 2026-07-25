function tokenValue(value) {
    if (value === null || value === undefined) return '';
    return String(value);
}

/**
 * Build {token → value} map from an Account show payload.
 * Explicit tokens win over custom_fields keys with the same name.
 */
export function buildAccountTemplateTokens(account, { assignedAgent = '' } = {}) {
    const custom = account?.custom_fields;
    const fromCustom = {};
    if (custom && typeof custom === 'object' && !Array.isArray(custom)) {
        for (const [key, value] of Object.entries(custom)) {
            if (/^[a-zA-Z0-9_]+$/.test(key)) {
                fromCustom[key] = tokenValue(value);
            }
        }
    }

    return {
        ...fromCustom,
        account_name: tokenValue(account?.account_name),
        account_number: tokenValue(account?.account_number),
        entity_name: tokenValue(account?.campaign?.entity?.name),
        campaign_name: tokenValue(account?.campaign?.name),
        date_acquired: tokenValue(account?.date_acquired),
        assigned_agent: tokenValue(assignedAgent),
        notes: tokenValue(account?.notes),
        product: tokenValue(account?.product),
        balance: tokenValue(account?.balance),
        due_date: tokenValue(account?.due_date),
        external_reference: tokenValue(account?.external_reference),
        entity_status: tokenValue(account?.entity_status?.name),
        entity_status_code: tokenValue(account?.entity_status?.code),
        entity_action_code: tokenValue(account?.entity_action_code?.name),
        entity_action_code_code: tokenValue(account?.entity_action_code?.code),
        last_reference_amount: tokenValue(account?.last_reference_amount),
        last_reference_date: tokenValue(account?.last_reference_date),
        last_reference_time: tokenValue(account?.last_reference_time),
        last_reference_text: tokenValue(account?.last_reference_text),
    };
}

/** Replace {token} placeholders; unknown tokens are left unchanged. */
export function resolveTemplateBody(body, tokens) {
    if (body == null || body === '') return '';
    return String(body).replace(/\{([a-zA-Z0-9_]+)\}/g, (match, name) =>
        Object.prototype.hasOwnProperty.call(tokens, name) ? tokens[name] : match,
    );
}
