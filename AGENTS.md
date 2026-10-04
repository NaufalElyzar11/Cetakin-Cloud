# Repository Engineering Rules

Cetakin Cloud is production-oriented software for the Cetakin 3D printing business and a software engineering portfolio project.

## Engineering principles

- Design for production: favor correctness, maintainability, reliability, and clear operational behavior.
- Use a modular monolith by default, with explicit module responsibilities and boundaries.
- Do not introduce premature microservices. Document a concrete need and tradeoffs before changing the default architecture.
- Require meaningful tests for implemented features and behavior changes. Run the relevant tests before reporting completion.
- Implementation agents must not claim completion when tests fail. Report failing or unrun tests and any remaining work explicitly.
- Consider security and authorization in every feature and architectural decision. Validate inputs, protect secrets, and enforce access permissions at trusted boundaries.
- Keep Git history clean through focused changes and commits. Do not commit generated artifacts, dependencies, or secrets, and do not rewrite shared history without authorization.
- Read the relevant project documentation in `docs/` and existing ADRs in `docs/adr/` before making architectural changes. Identify unresolved planning questions instead of inventing requirements.
- Record significant architectural decisions in `docs/adr/`, including context, the decision, alternatives, and consequences.

## Collaboration and current scope

- Examine proposals critically; identify weak assumptions and concrete risks before supporting them. Communicate directly and concisely.
- Preserve unrelated work and coordinate changes when multiple agents share the repository.
- The repository is currently in planning. Do not implement application code or install frameworks until explicitly authorized.
- Complete the planning documents in later planning stages using agreed requirements.
