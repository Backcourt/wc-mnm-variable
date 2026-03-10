// Initial State.
const DEFAULT_STATE = {
	basePrice: { price: 0, regular_price: 0 },
	context: 'add-to-cart',
	containers: {},
	containerId: null,
	// Base child items shared across all variations (static data like images, names, etc.).
	baseChildItems: [],
	// Child categories shared across all variations.
	childCategories: [],
	selections: [],
	config: {},
	totalQuantity: 0,
	messages: { status: [], errors: [] },
	passesValidation: false,
	subTotal: { price: 0, regular_price: 0 },
	total: { price: 0, regular_price: 0 },
};

export default DEFAULT_STATE;
