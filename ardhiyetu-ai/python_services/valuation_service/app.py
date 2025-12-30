from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import pandas as pd
import numpy as np
from datetime import datetime
import logging
import os

app = Flask(__name__)
CORS(app)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Load ML model
MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models/land_valuation_model.pkl')

try:
    model = joblib.load(MODEL_PATH)
    logger.info(f"Model loaded from {MODEL_PATH}")
except FileNotFoundError:
    model = None
    logger.warning("No trained model found. Using fallback method.")

class LandValuationModel:
    def __init__(self):
        self.county_prices = {
            'nairobi': 5000000,
            'kiambu': 3000000,
            'mombasa': 4000000,
            'kisumu': 2000000,
            'nakuru': 1500000,
            'default': 1000000
        }
        
        self.location_scores = {
            'cbd': 0.9,
            'suburb': 0.7,
            'rural': 0.4,
            'industrial': 0.6,
            'residential': 0.8
        }
        
        self.zoning_multipliers = {
            'residential': 1.0,
            'commercial': 1.5,
            'industrial': 1.2,
            'agricultural': 0.7,
            'mixed_use': 1.3
        }
    
    def preprocess_features(self, features):
        """Prepare features for ML model"""
        df = pd.DataFrame([features])
        
        # Encode categorical variables
        if 'county' in df.columns:
            df['county'] = df['county'].str.lower()
        
        if 'zoning' in df.columns:
            df['zoning'] = df['zoning'].str.lower()
            df['zoning_encoded'] = df['zoning'].map(self.zoning_multipliers).fillna(1.0)
        
        # Calculate location score
        if 'location' in df.columns:
            df['location_score'] = df['location'].apply(self._calculate_location_score)
        
        # Ensure all required columns exist
        required_columns = [
            'size', 'location_score', 'infrastructure', 
            'market_trend', 'proximity_amenities', 'soil_quality', 'accessibility'
        ]
        
        for col in required_columns:
            if col not in df.columns:
                df[col] = 0.5  # Default value
        
        return df
    
    def _calculate_location_score(self, location):
        """Calculate location score from text"""
        if not isinstance(location, str):
            return 0.5
        
        location_lower = location.lower()
        for keyword, score in self.location_scores.items():
            if keyword in location_lower:
                return score
        
        # Check for specific area indicators
        indicators = {
            'near': 0.6,
            'close to': 0.6,
            'adjacent': 0.7,
            'along': 0.5
        }
        
        for indicator, score in indicators.items():
            if indicator in location_lower:
                return score
        
        return 0.5
    
    def predict_with_ml(self, features):
        """Use ML model for prediction"""
        if model is None:
            return None
        
        try:
            df = self.preprocess_features(features)
            
            # Select features for model
            model_features = [
                'size', 'location_score', 'infrastructure', 
                'market_trend', 'proximity_amenities', 'soil_quality', 'accessibility'
            ]
            
            X = df[model_features]
            
            # Make prediction
            prediction = model.predict(X)[0]
            confidence = self.calculate_confidence(X)
            
            return {
                'value': float(prediction),
                'confidence': float(confidence),
                'method': 'ml_model'
            }
            
        except Exception as e:
            logger.error(f"ML prediction failed: {e}")
            return None
    
    def predict_with_rules(self, features):
        """Fallback rule-based prediction"""
        county = features.get('county', '').lower()
        base_price = self.county_prices.get(county, self.county_prices['default'])
        
        # Extract features
        size = float(features.get('size', 1))
        location_score = self._calculate_location_score(features.get('location', ''))
        infrastructure = float(features.get('infrastructure', 0.5))
        zoning = features.get('zoning', 'residential').lower()
        
        # Calculate adjustments
        zoning_multiplier = self.zoning_multipliers.get(zoning, 1.0)
        
        adjustments = (
            size * 0.8 +
            location_score * 1.2 +
            infrastructure * 0.5 +
            zoning_multiplier * 0.5
        )
        
        estimated_value = base_price * adjustments
        
        # Calculate confidence based on data completeness
        completeness = self.calculate_data_completeness(features)
        confidence = 0.5 + (completeness * 0.5)
        
        return {
            'value': float(estimated_value),
            'confidence': float(confidence),
            'method': 'rule_based',
            'factors': {
                'base_price': base_price,
                'size_impact': size * 0.8,
                'location_impact': location_score * 1.2,
                'zoning_impact': zoning_multiplier * 0.5
            }
        }
    
    def calculate_confidence(self, X):
        """Calculate prediction confidence"""
        # Simple confidence based on feature variance
        variance = X.var().mean()
        confidence = 0.7 - (variance * 0.5)
        return max(0.3, min(0.95, confidence))
    
    def calculate_data_completeness(self, features):
        """Calculate how complete the input data is"""
        required_fields = ['location', 'size', 'county']
        present_fields = [f for f in required_fields if f in features and features[f]]
        return len(present_fields) / len(required_fields)

# Initialize model
valuation_model = LandValuationModel()

@app.route('/api/ai/valuate', methods=['POST'])
def valuate_land():
    """API endpoint for land valuation"""
    try:
        data = request.get_json()
        
        if not data:
            return jsonify({'error': 'No data provided'}), 400
        
        logger.info(f"Valuation request: {data}")
        
        # Try ML model first
        ml_result = valuation_model.predict_with_ml(data)
        
        if ml_result and ml_result['confidence'] > 0.6:
            result = ml_result
        else:
            # Fallback to rule-based
            result = valuation_model.predict_with_rules(data)
        
        # Add metadata
        result['timestamp'] = datetime.now().isoformat()
        result['input_features'] = {k: v for k, v in data.items() if k not in ['api_key']}
        
        logger.info(f"Valuation result: {result}")
        
        return jsonify({
            'success': True,
            'data': result
        })
        
    except Exception as e:
        logger.error(f"Valuation error: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/api/ai/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model_loaded': model is not None,
        'timestamp': datetime.now().isoformat()
    })

@app.route('/api/ai/train', methods=['POST'])
def train_model():
    """Endpoint for model training"""
    try:
        data = request.get_json()
        
        if not data or 'training_data' not in data:
            return jsonify({'error': 'No training data provided'}), 400
        
        # This is a simplified training endpoint
        # In production, this would trigger an async training job
        
        return jsonify({
            'success': True,
            'message': 'Training job queued',
            'data_points': len(data['training_data']),
            'timestamp': datetime.now().isoformat()
        })
        
    except Exception as e:
        logger.error(f"Training error: {e}")
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=True)