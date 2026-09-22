-- Run with psql variables, for example:
-- psql "$DATABASE_URL" \
--   --set=user_email='user@example.com' \
--   --set=tenant_id='00000000-0000-0000-0000-000000000000' \
--   --set=tenant_role='member' \
--   --file=database/sql/upsert_tenant_user.sql

\set ON_ERROR_STOP on

BEGIN;

SELECT :'tenant_role' IN ('owner', 'admin', 'member') AS valid_role \gset

\if :valid_role
\else
    \echo 'Invalid tenant_role. Expected owner, admin, or member.'
    ROLLBACK;
    \quit
\endif

SELECT false AS membership_written \gset

INSERT INTO tenant_user (
    tenant_id,
    user_id,
    role,
    status,
    created_at,
    updated_at
)
SELECT
    t.id,
    u.id,
    :'tenant_role',
    'active',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM tenants AS t
CROSS JOIN users AS u
WHERE t.id = :'tenant_id'::uuid
  AND lower(u.email) = lower(:'user_email')
  AND t.status = 'active'
  AND u.status = 'active'
ON CONFLICT (tenant_id, user_id) DO UPDATE
SET role = EXCLUDED.role,
    status = 'active',
    updated_at = CURRENT_TIMESTAMP
RETURNING true AS membership_written \gset

\if :membership_written
\else
    \echo 'No active user/tenant match was found; no membership was written.'
    ROLLBACK;
    \quit
\endif

COMMIT;
