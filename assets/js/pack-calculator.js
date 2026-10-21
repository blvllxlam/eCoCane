(() => {
  const formatTotal = (amount, calculator) => {
    const decimals = Number(calculator.dataset.decimals);
    const fixedAmount = amount.toFixed(decimals);
    const [integerPart, decimalPart] = fixedAmount.split('.');
    const integer = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, calculator.dataset.thousandSeparator);
    const formattedNumber = decimals
      ? `${integer}${calculator.dataset.decimalSeparator}${decimalPart}`
      : integer;
    const symbol = calculator.dataset.currencySymbol;
    const position = calculator.dataset.currencyPosition;

    switch (position) {
      case 'right':
        return `${formattedNumber}${symbol}`;
      case 'left_space':
        return `${symbol} ${formattedNumber}`;
      case 'right_space':
        return `${formattedNumber} ${symbol}`;
      default:
        return `${symbol}${formattedNumber}`;
    }
  };

  const renderPrice = (container, text) => {
    const amount = document.createElement('span');
    amount.className = 'woocommerce-Price-amount amount';
    const bdi = document.createElement('bdi');
    bdi.textContent = text;
    amount.append(bdi);
    container.replaceChildren(amount);
  };

  const initCalculator = (calculator) => {
    if (calculator.dataset.loopCalculator === 'true') {
      const card = calculator.closest('li.product, .wc-block-product');
      const quantity = calculator.querySelector('input.ecocane-pack-calculator__quantity');
      const total = calculator.querySelector('[data-pack-total]');
      const packSize = Number(calculator.dataset.packSize);

      if (!card || !quantity || !total || !Number.isInteger(packSize) || packSize < 1) {
        return;
      }

      const addToCartButton = card.querySelector('.add_to_cart_button[data-product_id]');
      const unitPrice = Number(calculator.dataset.unitPrice);
      const updateTotal = () => {
        const count = Number.parseInt(quantity.value, 10) || 0;
        renderPrice(total, formatTotal(unitPrice * count, calculator));
      };
      const normalizeQuantity = () => {
        const requested = Number.parseInt(quantity.value, 10) || packSize;
        const normalized = Math.max(packSize, Math.ceil(requested / packSize) * packSize);
        const maximum = Number.parseInt(quantity.max, 10);
        quantity.value = Number.isFinite(maximum) && maximum >= packSize
          ? String(Math.min(normalized, Math.floor(maximum / packSize) * packSize))
          : String(normalized);
        updateTotal();
      };
      const updateButtonQuantity = () => {
        if (!addToCartButton) {
          return;
        }

        normalizeQuantity();
        addToCartButton.dataset.quantity = quantity.value;
        if (addToCartButton.href) {
          const url = new URL(addToCartButton.href, window.location.href);
          url.searchParams.set('quantity', quantity.value);
          addToCartButton.href = url.toString();
        }
      };

      calculator.querySelectorAll('[data-pack-direction]').forEach((button) => {
        button.addEventListener('click', () => {
          const current = Number.parseInt(quantity.value, 10);
          const baseQuantity = Number.isFinite(current)
            ? Math.max(packSize, Math.ceil(current / packSize) * packSize)
            : packSize;
          const direction = Number(button.dataset.packDirection);
          quantity.value = String(Math.max(packSize, baseQuantity + direction * packSize));
          normalizeQuantity();
        });
      });

      quantity.addEventListener('input', updateTotal);
      quantity.addEventListener('change', normalizeQuantity);
      addToCartButton?.addEventListener('click', updateButtonQuantity, true);
      updateTotal();
      return;
    }

    const form = calculator.closest('form.cart');
    const inputName = calculator.dataset.inputName;
    const quantity = inputName
      ? [...(form?.querySelectorAll('input.qty') || [])].find((input) => input.name === inputName)
      : form?.querySelector('input.qty');
    const total = calculator.querySelector('[data-pack-total]');
    const unitPriceElement = calculator.querySelector('[data-pack-unit-price]');
    const packSize = Number(calculator.dataset.packSize);
    const isOptional = calculator.dataset.optional === 'true';

    if (!form || !quantity || !total || !unitPriceElement || !Number.isInteger(packSize) || packSize < 1) {
      return;
    }

    let unitPrice = Number(calculator.dataset.unitPrice);
    quantity.min = String(packSize);
    quantity.step = String(packSize);

    const updateTotal = () => {
      const count = Number.parseInt(quantity.value, 10) || 0;
      renderPrice(total, formatTotal(unitPrice * count, calculator));
    };

    const updateUnitPrice = () => {
      renderPrice(unitPriceElement, formatTotal(unitPrice, calculator));
    };

    const normalizeQuantity = () => {
      if (isOptional && quantity.value.trim() === '') {
        updateTotal();
        return;
      }

      const requested = Number.parseInt(quantity.value, 10) || packSize;
      const normalized = Math.max(packSize, Math.ceil(requested / packSize) * packSize);
      const maximum = Number.parseInt(quantity.max, 10);
      quantity.value = Number.isFinite(maximum) && maximum >= packSize
        ? String(Math.min(normalized, Math.floor(maximum / packSize) * packSize))
        : String(normalized);
      updateTotal();
    };

    calculator.querySelectorAll('[data-pack-direction]').forEach((button) => {
      button.addEventListener('click', () => {
        const direction = Number(button.dataset.packDirection);
        const current = Number.parseInt(quantity.value, 10);
        if (isOptional && !Number.isFinite(current)) {
          if (direction > 0) {
            quantity.value = String(packSize);
            normalizeQuantity();
          } else {
            updateTotal();
          }
          return;
        }
        const baseQuantity = Number.isFinite(current)
          ? Math.max(packSize, Math.ceil(current / packSize) * packSize)
          : packSize;
        quantity.value = String(Math.max(packSize, baseQuantity + direction * packSize));
        normalizeQuantity();
      });
    });

    quantity.addEventListener('input', updateTotal);
    quantity.addEventListener('change', normalizeQuantity);
    form.addEventListener('submit', normalizeQuantity);
    updateUnitPrice();
    updateTotal();

    if (window.jQuery) {
      window.jQuery(form).on('found_variation', (_event, variation) => {
        quantity.min = String(packSize);
        quantity.step = String(packSize);
        if (Number.isFinite(Number(variation.display_price))) {
          unitPrice = Number(variation.display_price);
        }
        updateUnitPrice();
        normalizeQuantity();
      });

      window.jQuery(form).on('reset_data', () => {
        quantity.min = String(packSize);
        quantity.step = String(packSize);
        unitPrice = Number(calculator.dataset.unitPrice);
        updateUnitPrice();
        normalizeQuantity();
      });
    }
  };

  document.querySelectorAll('.ecocane-pack-calculator').forEach(initCalculator);

  document.querySelectorAll('.single-product form.cart:not(.grouped_form)').forEach((form) => {
    const calculator = form.querySelector('.ecocane-pack-calculator');
    const addToCartButton = form.querySelector('.single_add_to_cart_button');
    if (calculator && addToCartButton) {
      addToCartButton.classList.add('ecocane-pack-calculator__cart');
      calculator.append(addToCartButton);
    }
  });

  const ajaxCartParams = window.wc_add_to_cart_params;
  if (!ajaxCartParams?.wc_ajax_url || !window.jQuery) {
    return;
  }

  const showCartMessage = (form, message, isError = false) => {
    let notice = form.nextElementSibling;
    if (!notice?.classList.contains('ecocane-ajax-cart-notice')) {
      notice = document.createElement('div');
      notice.className = 'ecocane-ajax-cart-notice';
      form.after(notice);
    }

    notice.classList.toggle('is-error', isError);
    notice.setAttribute('role', isError ? 'alert' : 'status');
    notice.textContent = message;
  };

  const addToCart = (data) => window.jQuery.ajax({
    type: 'POST',
    url: ajaxCartParams.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
    data,
    dataType: 'json',
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('.single-product form.cart')) {
      return;
    }

    const button = event.submitter || form.querySelector('.single_add_to_cart_button');
    if (!button || button.disabled) {
      return;
    }

    event.preventDefault();
    if (button.dataset.ajaxPending === 'true') {
      return;
    }

    const originalDisabled = button.disabled;
    button.dataset.ajaxPending = 'true';
    button.disabled = true;
    button.classList.add('loading');
    button.setAttribute('aria-busy', 'true');

    const restoreButton = () => {
      button.disabled = originalDisabled;
      button.classList.remove('loading');
      button.removeAttribute('aria-busy');
      delete button.dataset.ajaxPending;
    };
    const triggerAddedToCart = (response) => {
      window.jQuery(document.body).trigger('added_to_cart', [
        response.fragments,
        response.cart_hash,
        window.jQuery(button),
      ]);
      button.classList.add('added');
    };
    const isSuccessfulResponse = (response) => Boolean(
      response && !response.error && response.fragments
    );

    try {
      if (form.matches('.grouped_form')) {
        const parentId = form.querySelector('input[name="add-to-cart"]')?.value;
        const quantities = [...form.querySelectorAll('input[name^="quantity["]')]
          .map((input) => {
            const match = input.name.match(/^quantity\[(\d+)\]$/);
            return match ? { productId: match[1], quantity: input.value } : null;
          })
          .filter((item) => item && Number(item.quantity) > 0);

        if (!parentId || quantities.length === 0) {
          showCartMessage(form, 'Выберите количество товара для добавления.', true);
          restoreButton();
          return;
        }

        let addedAny = false;
        let hadErrors = false;
        for (const item of quantities) {
          try {
            const response = await addToCart({
              product_id: item.productId,
              quantity: item.quantity,
              'add-to-cart': parentId,
            });
            if (!isSuccessfulResponse(response)) {
              hadErrors = true;
              continue;
            }
            addedAny = true;
            triggerAddedToCart(response);
          } catch {
            hadErrors = true;
          }
        }

        if (addedAny) {
          showCartMessage(
            form,
            hadErrors
              ? 'Некоторые товары не удалось добавить. Проверьте количество и наличие.'
              : 'Товары добавлены в корзину.',
            hadErrors
          );
        } else {
          showCartMessage(form, 'Не удалось добавить товары. Проверьте количество и наличие.', true);
        }
        restoreButton();
        return;
      }

      const variationId = form.querySelector('input.variation_id')?.value;
      const productId = variationId
        ? Number(variationId)
        : Number(button.value || form.querySelector('input[name="add-to-cart"]')?.value);
      const quantity = form.querySelector('input.qty')?.value || '1';

      if (!Number.isInteger(productId) || productId < 1) {
        showCartMessage(form, 'Сначала выберите вариант товара.', true);
        restoreButton();
        return;
      }

      const response = await addToCart({ product_id: productId, quantity });
      if (!isSuccessfulResponse(response)) {
        showCartMessage(form, 'Не удалось добавить товар. Проверьте количество и наличие.', true);
        restoreButton();
        return;
      }

      triggerAddedToCart(response);
      showCartMessage(form, 'Товар добавлен в корзину.');
      restoreButton();
    } catch {
      showCartMessage(form, 'Не удалось связаться с корзиной. Попробуйте ещё раз.', true);
      restoreButton();
    }
  });
})();
