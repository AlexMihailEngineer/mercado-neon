import express, { Request, Response } from 'express';
import { UblBuilder } from 'efactura-anaf-ts-sdk';

const app = express();

// Middleware to parse incoming JSON payloads from Laravel
app.use(express.json({ limit: '10mb' }));

// Health check endpoint
app.get('/health', (req: Request, res: Response) => {
    res.status(200).json({ status: 'ok', service: 'efactura-microservice' });
});

// The main generation endpoint
app.post('/generate-xml', (req: Request, res: Response) => {
    try {
        const invoiceData = req.body;

        if (!invoiceData || Object.keys(invoiceData).length === 0) {
            return res.status(400).json({ error: 'Empty invoice payload provided.' });
        }

        // 1. Initialize the builder with ZERO arguments (Fixes error TS2554 at line 24)
        const builder = new UblBuilder();
        
        // 2. Pass the data to the method instead (Fixes error TS2554 at line 27)
        const xmlString = builder.generateInvoiceXml(invoiceData);
        
        res.set('Content-Type', 'application/xml');
        res.status(200).send(xmlString);

    } catch (error: any) {
        console.error('XML Generation Error:', error.message);
        
        res.status(422).json({ 
            error: 'Failed to generate UBL XML', 
            details: error.message 
        });
    }
});

const PORT = process.env.PORT || 3000;

app.listen(PORT, () => {
    console.log(`🚀 ANAF UBL Microservice running on port ${PORT}`);
});