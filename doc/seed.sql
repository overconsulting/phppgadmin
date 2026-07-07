-- Jeu de données de démo, chargé automatiquement au premier démarrage du conteneur Postgres.
-- (Monté dans /docker-entrypoint-initdb.d/ — voir docker-compose.yml)

CREATE TABLE IF NOT EXISTS customers (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    full_name   VARCHAR(255) NOT NULL,
    note        TEXT,                              -- colonne nullable (démo de la case NULL)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS orders (
    id          SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id),
    amount      NUMERIC(10,2) NOT NULL,
    status      VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);

-- Rafraîchit updated_at à chaque UPDATE (PostgreSQL n'a pas d'équivalent natif à MySQL).
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_customers_updated_at ON customers;
CREATE TRIGGER trg_customers_updated_at BEFORE UPDATE ON customers
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_orders_updated_at ON orders;
CREATE TRIGGER trg_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

INSERT INTO customers (email, full_name) VALUES
    ('alice@example.com',  'Alice Martin'),
    ('bob@example.com',    'Bob Dupont'),
    ('carol@example.com',  'Carol Nguyen')
ON CONFLICT (email) DO NOTHING;

INSERT INTO orders (customer_id, amount, status) VALUES
    (1, 49.90,  'paid'),
    (1, 12.00,  'pending'),
    (2, 199.99, 'paid'),
    (3, 7.50,   'refunded')
ON CONFLICT DO NOTHING;

-- Une vue, pour vérifier que l'outil distingue tables et vues.
CREATE OR REPLACE VIEW customer_totals AS
SELECT c.id, c.full_name, COALESCE(SUM(o.amount), 0) AS total_spent
FROM customers c
LEFT JOIN orders o ON o.customer_id = c.id
GROUP BY c.id, c.full_name;
