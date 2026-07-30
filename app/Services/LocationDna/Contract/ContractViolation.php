<?php

namespace App\Services\LocationDna\Contract;

/**
 * ContractViolation — the closed set of reasons the contract core rejects an input.
 *
 * G1c contract core. INERT.
 *
 * Deliberately small. §6.3 of v1.2 defines the closed ERROR CODE set for the transport
 * envelope; this enum is the narrower domain-side vocabulary for contract violations the
 * inert core can detect without any transport, capability or authorisation context. It is
 * not a re-implementation of §6.3 and does not duplicate its codes.
 */
enum ContractViolation: string
{
    /** Top-level input is not an array, or a known dimension holds an unusable shape. */
    case MalformedDocument = 'malformed_document';

    /** schema_version is newer than this reader understands (§5.5 refuse-to-interpret). */
    case UnsupportedSchemaVersion = 'unsupported_schema_version';

    /** A dimension value fails its kind's shape or unit rule. */
    case InvalidDimensionValue = 'invalid_dimension_value';

    /** An operation was requested that the v1 vocabulary does not contain (§6.2). */
    case InvalidOperation = 'invalid_operation';

    /** Geometry is structurally invalid — path-less polygon, centre-less radius entry. */
    case InvalidGeometry = 'invalid_geometry';

    /** null was offered as an authored value. D-G1-1: null is never authored. */
    case AuthoredNull = 'authored_null';
}
