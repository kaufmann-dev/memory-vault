DELETE FROM "sessions";
--> statement-breakpoint
ALTER TABLE "sessions" ALTER COLUMN "user_id" DROP NOT NULL;
--> statement-breakpoint
ALTER TABLE "sessions" RENAME COLUMN "expires_at" TO "idle_expires_at";
--> statement-breakpoint
ALTER TABLE "sessions" ADD COLUMN "id_token" text NOT NULL;
--> statement-breakpoint
ALTER TABLE "sessions" ADD COLUMN "setup_email" text;
--> statement-breakpoint
ALTER TABLE "sessions" ADD COLUMN "setup_name" text;
--> statement-breakpoint
ALTER TABLE "sessions" ADD COLUMN "absolute_expires_at" timestamp NOT NULL;
--> statement-breakpoint
ALTER TABLE "users" DROP COLUMN "password_hash";
--> statement-breakpoint
CREATE TABLE "oidc_login_states" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"state_hash" text NOT NULL,
	"code_verifier" text NOT NULL,
	"nonce" text NOT NULL,
	"expires_at" timestamp NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE UNIQUE INDEX "oidc_login_states_state_hash_idx" ON "oidc_login_states" USING btree ("state_hash");
--> statement-breakpoint
CREATE INDEX "oidc_login_states_expires_at_idx" ON "oidc_login_states" USING btree ("expires_at");
