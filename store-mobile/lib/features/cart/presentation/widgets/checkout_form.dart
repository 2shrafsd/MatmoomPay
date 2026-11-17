import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/utils/customer_validators.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/features/cart/presentation/view_models/checkout_view_model.dart';
import 'package:matjar_app/l10n/generated/app_localizations.dart';

/// Name + phone form and the pay button.
class CheckoutForm extends ConsumerStatefulWidget {
  const CheckoutForm({required this.total, super.key});

  final int total;

  static const String nameField = 'customer_name';
  static const String phoneField = 'customer_phone';

  @override
  ConsumerState<CheckoutForm> createState() => _CheckoutFormState();
}

class _CheckoutFormState extends ConsumerState<CheckoutForm> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  void _dismissFailure() =>
      ref.read(checkoutViewModelProvider.notifier).dismissFailure();

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    final order = await ref
        .read(checkoutViewModelProvider.notifier)
        .submit(
          customerName: _nameController.text,
          customerPhone: _phoneController.text,
        );
    if (order == null || !mounted) {
      return;
    }
    await context.push(Routes.payment, extra: order);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    final state = ref.watch(checkoutViewModelProvider);
    final isSubmitting = state is CheckoutSubmitting;
    final failure = state is CheckoutFailed ? state.failure : null;
    final fieldErrors = failure is ValidationFailure
        ? failure.fieldErrors
        : const <String, String>{};
    final otherServerErrors = [
      for (final MapEntry(:key, :value) in fieldErrors.entries)
        if (key != CheckoutForm.nameField && key != CheckoutForm.phoneField)
          value,
    ];

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.customerDetailsTitle, style: textTheme.titleMedium),
          const SizedBox(height: AppSpacing.lg),
          TextFormField(
            controller: _nameController,
            enabled: !isSubmitting,
            textInputAction: TextInputAction.next,
            textCapitalization: TextCapitalization.words,
            autofillHints: const [AutofillHints.name],
            maxLength: CustomerValidators.nameMaxLength,
            decoration: InputDecoration(
              labelText: l10n.customerNameLabel,
              counterText: '',
            ),
            forceErrorText: fieldErrors[CheckoutForm.nameField],
            onChanged: (_) => _dismissFailure(),
            validator: (value) =>
                _nameErrorText(l10n, CustomerValidators.name(value)),
          ),
          const SizedBox(height: AppSpacing.lg),
          TextFormField(
            controller: _phoneController,
            enabled: !isSubmitting,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.done,
            autofillHints: const [AutofillHints.telephoneNumber],
            // Phone numbers read left to right in Arabic too.
            textDirection: TextDirection.ltr,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp('[0-9+ ]')),
            ],
            decoration: InputDecoration(
              labelText: l10n.customerPhoneLabel,
              hintText: l10n.customerPhoneHint,
              hintTextDirection: TextDirection.ltr,
            ),
            forceErrorText: fieldErrors[CheckoutForm.phoneField],
            onChanged: (_) => _dismissFailure(),
            validator: (value) =>
                _phoneErrorText(l10n, CustomerValidators.phone(value)),
            onFieldSubmitted: (_) => _submit(),
          ),
          if (failure != null) ...[
            const SizedBox(height: AppSpacing.lg),
            _FailureBanner(
              message: [
                failure.localizedMessage(l10n),
                ...otherServerErrors,
              ].join('\n'),
            ),
          ],
          const SizedBox(height: AppSpacing.xxl),
          PrimaryButton(
            label: l10n.payAmount(l10n.sdg(widget.total)),
            isBusy: isSubmitting,
            onPressed: _submit,
          ),
          const SizedBox(height: AppSpacing.lg),
          _Note(icon: Icons.lock_outline, text: l10n.paymentSecureNote),
          const SizedBox(height: AppSpacing.sm),
          _Note(icon: Icons.credit_card_outlined, text: l10n.testCardHint),
        ],
      ),
    );
  }

  String? _nameErrorText(AppLocalizations l10n, CustomerNameError? error) =>
      switch (error) {
        CustomerNameError.required => l10n.nameRequired,
        CustomerNameError.tooLong => l10n.nameTooLong,
        null => null,
      };

  String? _phoneErrorText(AppLocalizations l10n, CustomerPhoneError? error) =>
      switch (error) {
        CustomerPhoneError.required => l10n.phoneRequired,
        CustomerPhoneError.invalid => l10n.phoneInvalid,
        null => null,
      };
}

class _Note extends StatelessWidget {
  const _Note({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final muted = context.palette.mutedText;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: AppSizes.iconSm, color: muted),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            text,
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: muted),
          ),
        ),
      ],
    );
  }
}

class _FailureBanner extends StatelessWidget {
  const _FailureBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Semantics(
      liveRegion: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: scheme.errorContainer,
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        child: Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.md),
          child: Text(
            message,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: scheme.onErrorContainer),
          ),
        ),
      ),
    );
  }
}
